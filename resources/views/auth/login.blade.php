@extends('layouts.app')

@section('title', __('auth.login.title'))

@section('content')
    <div class="mx-auto max-w-md">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-secondary-500 dark:text-white">{{ __('auth.login.title') }}</h1>
            <p class="mt-1 text-gray-400 dark:text-gray-400">{{ __('auth.login.subtitle') }}</p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-md dark:border-gray-600 dark:bg-secondary-500">
            <livewire:forms.login-form />
        </div>
    </div>
@endsection
