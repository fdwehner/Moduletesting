<div>
    @if ($open)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 px-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-md rounded-lg border border-gray-200 bg-white p-6 shadow-lg dark:border-gray-600 dark:bg-secondary-500">
                <h2 class="text-xl font-semibold text-secondary-500 dark:text-white">{{ $title }}</h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-300">{{ $message }}</p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="cancel" class="rounded-lg border border-gray-200 px-4 py-2 dark:border-gray-600">
                        {{ __('common.actions.cancel') }}
                    </button>
                    <button type="button" wire:click="confirm" class="rounded-lg bg-red-600 px-4 py-2 text-white">
                        {{ __('common.actions.confirm') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
