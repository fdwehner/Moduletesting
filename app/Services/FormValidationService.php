<?php

namespace App\Services;

use InvalidArgumentException;

class FormValidationService
{
    /**
     * @return array<string, mixed>
     */
    public function getValidationRules(string $form): array
    {
        return match ($form) {
            'login' => [
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => ['required', 'string'],
            ],
            'register' => [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ],
            default => throw new InvalidArgumentException('Unknown form: '.$form),
        };
    }

    /**
     * @return array<string, string>
     */
    public function getValidationMessages(string $form): array
    {
        return match ($form) {
            'login' => [
                'email.required' => __('auth.forms.email_required'),
                'password.required' => __('auth.forms.password_required'),
            ],
            'register' => [
                'name.required' => __('auth.forms.name_required'),
                'email.required' => __('auth.forms.email_required'),
                'email.unique' => __('auth.forms.email_unique'),
                'password.required' => __('auth.forms.password_required'),
                'password.confirmed' => __('auth.forms.password_confirmed'),
            ],
            default => [],
        };
    }
}
