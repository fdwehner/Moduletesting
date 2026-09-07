<form wire:submit="login" class="space-y-4">
    <div>
        <label class="mb-2 block text-sm font-medium text-secondary-500 dark:text-gray-200">{{ __('auth.forms.email') }}</label>
        <input wire:model.blur="email" type="email" autocomplete="username" class="w-full rounded-lg border border-gray-200 px-4 py-3 text-base touch-manipulation dark:border-gray-400 dark:bg-secondary-600">
        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="mb-2 block text-sm font-medium text-secondary-500 dark:text-gray-200">{{ __('auth.forms.password') }}</label>
        <input wire:model.blur="password" type="password" autocomplete="current-password" class="w-full rounded-lg border border-gray-200 px-4 py-3 text-base touch-manipulation dark:border-gray-400 dark:bg-secondary-600">
        @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <label class="flex items-center gap-2 text-sm">
        <input wire:model="remember" type="checkbox" class="rounded border-gray-300">
        {{ __('auth.login.remember') }}
    </label>
    <button type="submit" wire:loading.attr="disabled" class="w-full rounded-lg bg-secondary-500 px-4 py-2 text-white dark:bg-white dark:text-secondary-500">
        <span wire:loading.remove wire:target="login">{{ __('auth.login.submit') }}</span>
        <span wire:loading wire:target="login">{{ __('common.actions.loading') }}</span>
    </button>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('auth.login.no_account') }}
        <a href="{{ route('register') }}" class="underline">{{ __('common.actions.register') }}</a>
    </p>
</form>
