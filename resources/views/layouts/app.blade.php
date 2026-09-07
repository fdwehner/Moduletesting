<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name'))</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @fonts
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
        @livewireStyles
        <style>[x-cloak] { display: none !important; }</style>
    </head>
    <body
        class="min-h-screen bg-gray-50 text-secondary-500 antialiased dark:bg-secondary-500 dark:text-white"
        x-data="{ toast: { show: false, message: '', type: 'success' } }"
        @toast.window="toast.show = true; toast.type = $event.detail.type ?? 'success'; toast.message = $event.detail.message ?? ''; setTimeout(() => toast.show = false, 4000)"
    >
        <header class="border-b border-gray-200 bg-white dark:border-gray-600 dark:bg-secondary-500">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-6 py-4">
                <a href="{{ auth()->check() ? route('dashboard') : route('home') }}" class="text-lg font-semibold">
                    {{ config('app.name') }}
                </a>

                <nav class="flex flex-wrap items-center gap-2">
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-secondary-600">
                            {{ __('app.dashboard.nav') }}
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-lg px-3 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-secondary-600">
                                {{ __('common.actions.logout') }}
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg px-3 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-secondary-600">
                            {{ __('common.actions.login') }}
                        </a>
                        <a href="{{ route('register') }}" class="rounded-lg bg-secondary-500 px-3 py-1.5 text-sm text-white dark:bg-white dark:text-secondary-500">
                            {{ __('common.actions.register') }}
                        </a>
                    @endauth

                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('app.welcome.switch_language') }}</span>
                    @foreach (config('app.available_locales') as $locale)
                        <form method="POST" action="{{ route('locale.update', ['locale' => $locale]) }}">
                            @csrf
                            <button
                                type="submit"
                                class="rounded-lg px-3 py-1.5 text-sm {{ app()->getLocale() === $locale ? 'bg-secondary-500 text-white dark:bg-white dark:text-secondary-500' : 'border border-gray-200 text-secondary-500 hover:bg-gray-50 dark:border-gray-600 dark:text-white dark:hover:bg-secondary-600' }}"
                            >
                                {{ __('common.locales.'.$locale) }}
                            </button>
                        </form>
                    @endforeach
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-10">
            @yield('content')
        </main>

        <div
            x-cloak
            x-show="toast.show"
            x-transition
            class="fixed right-4 top-4 z-50 max-w-sm rounded-lg px-4 py-3 shadow-lg"
            :class="toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-secondary-500 text-white dark:bg-white dark:text-secondary-500'"
            role="status"
        >
            <p x-text="toast.message"></p>
        </div>

        @livewireScripts
    </body>
</html>
