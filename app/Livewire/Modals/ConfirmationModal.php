<?php

namespace App\Livewire\Modals;

use Livewire\Attributes\On;
use Livewire\Component;

class ConfirmationModal extends Component
{
    public bool $open = false;

    public string $title = '';

    public string $message = '';

    public string $confirmEvent = '';

    public mixed $payload = null;

    #[On('open-confirmation')]
    public function openModal(string $title, string $message, string $confirmEvent, mixed $payload = null): void
    {
        $this->title = $title;
        $this->message = $message;
        $this->confirmEvent = $confirmEvent;
        $this->payload = $payload;
        $this->open = true;
    }

    public function confirm(): void
    {
        if ($this->confirmEvent === '') {
            $this->open = false;

            return;
        }

        if ($this->payload === null) {
            $this->dispatch($this->confirmEvent);
        } else {
            $this->dispatch($this->confirmEvent, id: $this->payload);
        }

        $this->open = false;
    }

    public function cancel(): void
    {
        $this->open = false;
        $this->payload = null;
        $this->confirmEvent = '';
    }

    public function render()
    {
        return view('livewire.modals.confirmation-modal');
    }
}
