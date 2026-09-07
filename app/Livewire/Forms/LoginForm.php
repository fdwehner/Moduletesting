<?php

namespace App\Livewire\Forms;

use App\Traits\FormValidationTrait;
use App\Traits\LogsActivity;
use App\Traits\WithToastNotifications;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class LoginForm extends Component
{
    use FormValidationTrait;
    use LogsActivity;
    use WithToastNotifications;

    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    /**
     * @return array<string, mixed>
     */
    protected function getValidationRules(): array
    {
        return $this->getValidationService()->getValidationRules('login');
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationMessages(): array
    {
        return $this->getValidationService()->getValidationMessages('login');
    }

    public function login(): void
    {
        $this->validate();
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        session()->regenerate();

        $this->redirect(route('org-designer.index'), navigate: true);
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $seconds]),
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }

    public function render()
    {
        return view('livewire.forms.login-form');
    }
}
