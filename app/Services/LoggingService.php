<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class LoggingService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $level, string $message, array $context = [], ?string $channel = null): void
    {
        $safeContext = $this->redact($context);

        Log::channel($channel ?? (string) config('logging.default'))->{$level}($message, $safeContext);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function crud(string $action, Model $model, array $context = []): void
    {
        $this->log('info', class_basename($model).' '.$action, array_merge([
            'model' => $model::class,
            'id' => $model->getKey(),
            'user_id' => auth()->id(),
        ], $context));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function redact(array $context): array
    {
        $sensitive = ['password', 'password_confirmation', 'token', 'api_key', '_token'];

        foreach ($sensitive as $key) {
            if (array_key_exists($key, $context)) {
                $context[$key] = '[redacted]';
            }
        }

        return $context;
    }
}
