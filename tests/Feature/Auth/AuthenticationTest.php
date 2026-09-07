<?php

namespace Tests\Feature\Auth;

use App\Livewire\Forms\LoginForm;
use App\Livewire\Forms\RegisterForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_login_and_register_pages(): void
    {
        $this->get(route('login'))->assertOk()->assertSee(__('auth.login.title'), false);
        $this->get(route('register'))->assertOk()->assertSee(__('auth.register.title'), false);
    }

    public function test_users_can_register_and_are_redirected_to_the_dashboard(): void
    {
        Livewire::test(RegisterForm::class)
            ->set('name', 'Ada Lovelace')
            ->set('email', 'ada@example.com')
            ->set('password', 'password12')
            ->set('passwordConfirmation', 'password12')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('org-designer.index'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    }

    public function test_users_can_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'password12',
        ]);

        Livewire::test(LoginForm::class)
            ->set('email', 'ada@example.com')
            ->set('password', 'password12')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('org-designer.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'ada@example.com',
            'password' => 'password12',
        ]);

        Livewire::test(LoginForm::class)
            ->set('email', 'ada@example.com')
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_authenticated_users_are_redirected_away_from_guest_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('login'))->assertRedirect(route('org-designer.index'));
        $this->actingAs($user)->get(route('home'))->assertOk();
    }
}
