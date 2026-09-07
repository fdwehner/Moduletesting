<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-secondary-500 dark:text-white">{{ __('org_designer.public.title') }}</h1>
            <p class="text-gray-400 dark:text-gray-400 mt-1">{{ __('org_designer.public.subtitle') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            @auth
                <a href="{{ route('org-designer.index') }}" class="rounded-lg bg-secondary-500 px-4 py-2 text-white dark:bg-white dark:text-secondary-500">
                    {{ __('org_designer.charts.title') }}
                </a>
            @else
                <a href="{{ route('login') }}" class="rounded-lg border border-gray-200 px-4 py-2 dark:border-gray-600">
                    {{ __('common.actions.login') }}
                </a>
            @endauth
        </div>
    </div>

    <div class="mb-6 bg-white dark:bg-secondary-500 rounded-lg shadow border border-gray-200 dark:border-gray-600" x-data="{ filtersOpen: @js($filtersOpen) }">
        <button type="button" @click="filtersOpen = !filtersOpen" class="flex w-full items-center gap-2 px-4 py-3 text-left">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 12h12M10 20h4" /></svg>
            <span class="font-medium">{{ __('common.actions.search') }} / {{ __('common.actions.filter') }}</span>
            @if ($hasActiveFilters)
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs dark:bg-secondary-600">{{ $charts->total() }}</span>
            @endif
        </button>
        <div x-show="filtersOpen" x-cloak class="border-t border-gray-200 px-4 py-4 dark:border-gray-600">
            <label class="block text-sm font-medium text-secondary-500 dark:text-gray-200 mb-2" for="public-chart-search">{{ __('common.actions.search') }}</label>
            <input
                id="public-chart-search"
                type="search"
                wire:model.live.debounce.500ms="search"
                class="w-full px-4 py-3 text-base border border-gray-200 rounded-lg dark:border-gray-600 dark:bg-secondary-600 touch-manipulation"
                placeholder="{{ __('org_designer.public.search_placeholder') }}"
            />
            @if ($hasActiveFilters)
                <button type="button" wire:click="clearFilters" class="mt-3 text-sm underline">{{ __('common.actions.clear_filters') }}</button>
            @endif
        </div>
    </div>

    <div wire:loading class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-secondary-500"></span>
        {{ __('common.actions.loading') }}
    </div>

    <div wire:loading.remove class="bg-white dark:bg-secondary-500 rounded-lg shadow-md border border-gray-200 dark:border-gray-600 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 dark:divide-gray-600 min-w-full">
                <thead class="bg-gray-50 dark:bg-secondary-600">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('org_designer.charts.table.name') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('org_designer.public.author') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('org_designer.public.published_at') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('org_designer.charts.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                    @forelse ($charts as $chart)
                        <tr wire:key="public-chart-{{ $chart->id }}" class="hover:bg-gray-50 dark:hover:bg-secondary-600 transition-colors">
                            <td class="px-4 py-3 font-medium">
                                <a href="{{ route('org-charts.show', $chart) }}">{{ $chart->name }}</a>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('org_designer.charts.counts', $chart->counts()) }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $chart->user?->name }}</td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap">{{ $chart->published_at?->timezone(config('app.timezone'))->isoFormat('LLL') }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('org-charts.show', $chart) }}" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-secondary-500">{{ __('common.actions.view') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">{{ __('org_designer.public.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($charts->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-600">
                {{ $charts->links() }}
            </div>
        @endif
    </div>
</div>
