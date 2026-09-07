<?php

namespace App\Livewire\Forms;

use App\Models\User;
use App\Traits\FormValidationTrait;
use App\Traits\LogsActivity;
use App\Traits\WithToastNotifications;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class RegisterForm extends Component
{
    use FormValidationTrait;
    use LogsActivity;
    use WithToastNotifications;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    /**
     * @return array<string, mixed>
     */
    protected function getValidationRules(): array
    {
        $rules = $this->getValidationService()->getValidationRules('register');
        $rules['password'] = ['required', 'string', 'min:8', 'same:passwordConfirmation'];
        $rules['passwordConfirmation'] = ['required', 'string'];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function getValidationMessages(): array
    {
        return $this->getValidationService()->getValidationMessages('register');
    }

    public function register(): void
    {
        try {
            $validated = $this->validate();

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            event(new Registered($user));

            Auth::login($user);
            session()->regenerate();

            $this->logCrud('created', $user, [
                'email' => $user->email,
            ]);

            $this->redirect(route('dashboard'), navigate: true);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->logError('Failed to register user', [
                'error' => $exception->getMessage(),
                'email' => $this->email,
            ]);
            $this->toastError(__('common.messages.error'));
        }
    }

    public function render()
    {
        return view('livewire.forms.register-form');
    }
}
