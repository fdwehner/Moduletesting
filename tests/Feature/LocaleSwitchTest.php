<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    public function test_locale_can_be_switched_to_german(): void
    {
        $this->from(route('home'))
            ->post(route('locale.update', ['locale' => 'de']))
            ->assertRedirect(route('home'));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('app.welcome.subtitle', locale: 'de'), false);
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $this->from(route('home'))
            ->post(route('locale.update', ['locale' => 'fr']))
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors('locale');
    }
}
