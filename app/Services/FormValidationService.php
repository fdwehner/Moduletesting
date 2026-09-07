<?php

namespace App\Services;

use App\Support\UploadRules;
use Illuminate\Validation\Rule;
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
            'org_designer_position' => [
                'role' => ['required', 'string', 'max:120'],
                'grade' => ['nullable', 'string', 'max:40'],
                'fte' => ['nullable', 'string', 'max:20'],
                'int_ext' => ['required', 'string', Rule::in(['Internal', 'External'])],
                'location' => ['nullable', 'string', 'max:80'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            'org_designer_team' => [
                'name' => ['required', 'string', 'max:120'],
                'topology' => ['required', 'string', Rule::in(['stream-aligned', 'enabling', 'platform', 'complicated-subsystem'])],
                'product_1' => ['nullable', 'string', 'max:120'],
                'product_2' => ['nullable', 'string', 'max:120'],
                'product_3' => ['nullable', 'string', 'max:120'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            'org_designer_head' => [
                'role' => ['nullable', 'string', 'max:120'],
                'grade' => ['nullable', 'string', 'max:40'],
                'int_ext' => ['required', 'string', Rule::in(['Internal', 'External'])],
                'location' => ['nullable', 'string', 'max:80'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            'org_designer_excel' => [
                'excelFile' => UploadRules::excelWorkbook(),
            ],
            'org_chart_name' => [
                'name' => ['required', 'string', 'max:120'],
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
            'org_designer_position' => [
                'role.required' => __('org_designer.validation.role_required'),
            ],
            'org_designer_team' => [
                'name.required' => __('org_designer.validation.team_name_required'),
                'topology.in' => __('org_designer.validation.topology_invalid'),
            ],
            'org_chart_name' => [
                'name.required' => __('org_designer.validation.chart_name_required'),
            ],
            default => [],
        };
    }
}
