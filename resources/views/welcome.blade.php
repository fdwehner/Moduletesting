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

    <div class="mt-10 mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-secondary-500 dark:text-white">{{ __('org_designer.public.title') }}</h2>
            <p class="text-gray-400 dark:text-gray-400 mt-1">{{ __('org_designer.public.subtitle') }}</p>
        </div>
        <a href="{{ route('org-charts.public-index') }}" class="rounded-lg border border-gray-200 px-4 py-2 dark:border-gray-600">
            {{ __('org_designer.public.nav') }}
        </a>
    </div>

    <div class="bg-white dark:bg-secondary-500 rounded-lg shadow-md border border-gray-200 dark:border-gray-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 dark:divide-gray-600 min-w-full">
                <thead class="bg-gray-50 dark:bg-secondary-600">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('org_designer.charts.table.name') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('org_designer.public.author') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('org_designer.charts.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                    @forelse ($publishedCharts as $chart)
                        <tr class="hover:bg-gray-50 dark:hover:bg-secondary-600 transition-colors">
                            <td class="px-4 py-3 font-medium">
                                <a href="{{ route('org-charts.show', $chart) }}">{{ $chart->name }}</a>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $chart->user?->name }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('org-charts.show', $chart) }}" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-secondary-500">{{ __('common.actions.view') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">{{ __('org_designer.public.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
