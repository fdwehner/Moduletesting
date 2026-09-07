<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', __('org_designer.title'))</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @fonts
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
        @livewireStyles
        <style>[x-cloak] { display: none !important; }</style>
    </head>
    <body
        class="h-full bg-gray-50 text-secondary-500 antialiased dark:bg-secondary-500 dark:text-white"
        x-data="{ toast: { show: false, message: '', type: 'success' } }"
        @toast.window="toast.show = true; toast.type = $event.detail.type ?? 'success'; toast.message = $event.detail.message ?? ''; setTimeout(() => toast.show = false, 4000)"
    >
        @yield('content')

        <div
            x-cloak
            x-show="toast.show"
            x-transition
            class="fixed right-4 top-4 z-[80] max-w-sm rounded-lg px-4 py-3 shadow-lg"
            :class="toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-secondary-500 text-white dark:bg-white dark:text-secondary-500'"
            role="status"
        >
            <p x-text="toast.message"></p>
        </div>

        @livewireScripts
    </body>
</html>
