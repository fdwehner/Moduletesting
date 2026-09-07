<form wire:submit="register" class="space-y-4">
    <div>
        <label class="mb-2 block text-sm font-medium text-secondary-500 dark:text-gray-200">{{ __('auth.forms.name') }}</label>
        <input wire:model.blur="name" type="text" autocomplete="name" class="w-full rounded-lg border border-gray-200 px-4 py-3 text-base touch-manipulation dark:border-gray-400 dark:bg-secondary-600">
        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-secondary-500 dark:text-gray-200">{{ __('auth.forms.email') }}</label>
        <input wire:model.blur="email" type="email" autocomplete="username" class="w-full rounded-lg border border-gray-200 px-4 py-3 text-base touch-manipulation dark:border-gray-400 dark:bg-secondary-600">
        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-secondary-500 dark:text-gray-200">{{ __('auth.forms.password') }}</label>
        <input wire:model.blur="password" type="password" autocomplete="new-password" class="w-full rounded-lg border border-gray-200 px-4 py-3 text-base touch-manipulation dark:border-gray-400 dark:bg-secondary-600">
        @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-secondary-500 dark:text-gray-200">{{ __('auth.forms.password_confirmation') }}</label>
        <input wire:model.blur="passwordConfirmation" type="password" autocomplete="new-password" class="w-full rounded-lg border border-gray-200 px-4 py-3 text-base touch-manipulation dark:border-gray-400 dark:bg-secondary-600">
        @error('passwordConfirmation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <button type="submit" wire:loading.attr="disabled" class="w-full rounded-lg bg-secondary-500 px-4 py-2 text-white dark:bg-white dark:text-secondary-500">
        <span wire:loading.remove wire:target="register">{{ __('auth.register.submit') }}</span>
        <span wire:loading wire:target="register">{{ __('common.actions.loading') }}</span>
    </button>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('auth.register.has_account') }}
        <a href="{{ route('login') }}" class="underline">{{ __('common.actions.login') }}</a>
    </p>
</form>
