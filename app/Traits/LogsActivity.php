<?php

namespace App\Traits;

use App\Services\LoggingService;
use Illuminate\Database\Eloquent\Model;

trait LogsActivity
{
    /**
     * @param  array<string, mixed>  $context
     */
    protected function logCrud(string $action, Model $model, array $context = []): void
    {
        $this->loggingService()->crud($action, $model, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->loggingService()->error($message, $context);
    }

    private function loggingService(): LoggingService
    {
        return app(LoggingService::class);
    }
}
