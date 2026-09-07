<?php

namespace App\Traits;

use App\Services\FormValidationService;
use Illuminate\Support\Str;

trait FormValidationTrait
{
    protected function getValidationService(): FormValidationService
    {
        return app(FormValidationService::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->mapKeysToCamelCase($this->getValidationRules());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->mapKeysToCamelCase($this->getValidationMessages());
    }

    public function updated(string $propertyName): void
    {
        $this->validateOnly($propertyName);
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function getValidationRules(): array;

    /**
     * @return array<string, string>
     */
    protected function getValidationMessages(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function mapKeysToCamelCase(array $rules): array
    {
        $mapped = [];

        foreach ($rules as $key => $rule) {
            $mapped[$this->toLivewireKey($key)] = $rule;
        }

        return $mapped;
    }

    private function toLivewireKey(string $key): string
    {
        $segments = explode('.', $key);
        $segments[0] = Str::camel($segments[0]);

        return implode('.', $segments);
    }
}
