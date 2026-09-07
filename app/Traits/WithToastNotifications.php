<?php

namespace App\Traits;

trait WithToastNotifications
{
    public function toastSuccess(string $message): void
    {
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function toastError(string $message): void
    {
        $this->dispatch('toast', type: 'error', message: $message);
    }
}
