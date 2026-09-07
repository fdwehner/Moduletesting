@extends('layouts.app')

@section('title', __('app.welcome.title'))

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-secondary-500 dark:text-white">{{ __('app.welcome.title') }}</h1>
            <p class="mt-1 text-gray-400 dark:text-gray-400">{{ __('app.welcome.subtitle') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            @auth
                <a href="{{ route('org-designer.index') }}" class="rounded-lg bg-secondary-500 px-4 py-2 text-white dark:bg-white dark:text-secondary-500">
                    {{ __('app.welcome.cta_dashboard') }}
                </a>
            @else
                <a href="{{ route('login') }}" class="rounded-lg border border-gray-200 px-4 py-2 dark:border-gray-600">
                    {{ __('app.welcome.cta_login') }}
                </a>
                <a href="{{ route('register') }}" class="rounded-lg bg-secondary-500 px-4 py-2 text-white dark:bg-white dark:text-secondary-500">
                    {{ __('app.welcome.cta_register') }}
                </a>
            @endauth
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-md dark:border-gray-600 dark:bg-secondary-500">
        <p class="text-base text-secondary-500 dark:text-gray-200">
            {{ __('app.welcome.contributing') }}
        </p>
    </div>
@endsection
