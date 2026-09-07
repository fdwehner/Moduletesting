@extends('layouts.app')

@section('title', __('app.dashboard.title'))

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-secondary-500 dark:text-white">{{ __('app.dashboard.title') }}</h1>
            <p class="mt-1 text-gray-400 dark:text-gray-400">{{ __('app.dashboard.subtitle') }}</p>
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-md dark:border-gray-600 dark:bg-secondary-500">
        <p class="text-base text-secondary-500 dark:text-gray-200">
            {{ __('app.dashboard.body') }}
        </p>
    </div>
@endsection
