<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-secondary-500 dark:text-white">{{ __('org_designer.charts.title') }}</h1>
            <p class="text-gray-400 dark:text-gray-400 mt-1">{{ __('org_designer.charts.subtitle') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <a href="{{ route('org-charts.public-index') }}" class="rounded-lg border border-gray-200 px-4 py-2 dark:border-gray-600">
                {{ __('org_designer.public.nav') }}
            </a>
            @can('create', \App\Models\OrgProject::class)
                <button type="button" wire:click="createChart" class="rounded-lg bg-secondary-500 px-4 py-2 text-white dark:bg-white dark:text-secondary-500">
                    {{ __('org_designer.charts.create') }}
                </button>
            @endcan
        </div>
    </div>

    <div class="mb-3 bg-white dark:bg-secondary-500 rounded-lg shadow border border-gray-200 dark:border-gray-600" x-data="{ statsOpen: false }">
        <button
            type="button"
            data-collapsed-panel-toggle
            @click="statsOpen = !statsOpen"
            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
        >
            <span class="flex items-center gap-2 font-medium text-secondary-500 dark:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3v18M5 8h14M7 12h10M9 16h6" /></svg>
                {{ __('org_designer.charts.tab_statistics') }}
            </span>
            <span class="flex flex-wrap gap-2 text-xs">
                <span class="rounded-full bg-gray-100 px-2 py-1 dark:bg-secondary-600">{{ __('org_designer.charts.stat_total') }}: {{ $stats['total'] }}</span>
                <span class="rounded-full bg-gray-100 px-2 py-1 dark:bg-secondary-600">{{ __('org_designer.charts.stat_published') }}: {{ $stats['published'] }}</span>
                <span class="rounded-full bg-gray-100 px-2 py-1 dark:bg-secondary-600">{{ __('org_designer.charts.stat_draft') }}: {{ $stats['draft'] }}</span>
            </span>
        </button>
        <div x-show="statsOpen" x-cloak x-transition class="grid gap-3 border-t border-gray-200 px-4 py-4 sm:grid-cols-3 dark:border-gray-600">
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-600">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('org_designer.charts.stat_total') }}</div>
                <div class="mt-1 text-2xl font-semibold">{{ $stats['total'] }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-600">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('org_designer.charts.stat_published') }}</div>
                <div class="mt-1 text-2xl font-semibold">{{ $stats['published'] }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-600">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('org_designer.charts.stat_draft') }}</div>
                <div class="mt-1 text-2xl font-semibold">{{ $stats['draft'] }}</div>
            </div>
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
        <div x-show="filtersOpen" x-cloak class="grid gap-4 border-t border-gray-200 px-4 py-4 sm:grid-cols-2 dark:border-gray-600">
            <div>
                <label class="block text-sm font-medium text-secondary-500 dark:text-gray-200 mb-2" for="org-chart-search">{{ __('common.actions.search') }}</label>
                <input
                    id="org-chart-search"
                    type="search"
                    wire:model.live.debounce.500ms="search"
                    class="w-full px-4 py-3 text-base border border-gray-200 rounded-lg dark:border-gray-600 dark:bg-secondary-600 touch-manipulation"
                    placeholder="{{ __('org_designer.charts.search_placeholder') }}"
                />
            </div>
            <div>
                <label class="block text-sm font-medium text-secondary-500 dark:text-gray-200 mb-2" for="org-chart-status">{{ __('org_designer.charts.status') }}</label>
                <select id="org-chart-status" wire:model.live="status" class="w-full px-4 py-3 text-base border border-gray-200 rounded-lg dark:border-gray-600 dark:bg-secondary-600 touch-manipulation">
                    <option value="">{{ __('org_designer.charts.status_all') }}</option>
                    <option value="draft">{{ __('org_designer.charts.status_draft') }}</option>
                    <option value="published">{{ __('org_designer.charts.status_published') }}</option>
                </select>
            </div>
            @if ($hasActiveFilters)
                <div class="sm:col-span-2">
                    <button type="button" wire:click="clearFilters" class="text-sm underline">{{ __('common.actions.clear_filters') }}</button>
                </div>
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
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('org_designer.charts.table.status') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('org_designer.charts.table.updated') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('org_designer.charts.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                    @forelse ($charts as $chart)
                        <tr wire:key="chart-{{ $chart->id }}" class="hover:bg-gray-50 dark:hover:bg-secondary-600 transition-colors">
                            <td class="px-4 py-3">
                                <a href="{{ route('org-designer.edit', $chart) }}" class="font-medium text-secondary-500 dark:text-white">{{ $chart->name }}</a>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('org_designer.charts.counts', $chart->counts()) }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if ($chart->isPublished())
                                    {{ __('org_designer.charts.status_published') }}
                                @else
                                    {{ __('org_designer.charts.status_draft') }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap">{{ $chart->updated_at?->timezone(config('app.timezone'))->isoFormat('LLL') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <a href="{{ route('org-designer.edit', $chart) }}" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-secondary-500" title="{{ __('common.actions.edit') }}">{{ __('common.actions.edit') }}</a>
                                    @if ($chart->isPublished())
                                        <a href="{{ route('org-charts.show', $chart) }}" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-secondary-500" title="{{ __('common.actions.view') }}">{{ __('org_designer.charts.view_public') }}</a>
                                        <button type="button" wire:click="unpublish({{ $chart->id }})" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-secondary-500">{{ __('org_designer.charts.unpublish') }}</button>
                                    @else
                                        <button type="button" wire:click="publish({{ $chart->id }})" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-secondary-500">{{ __('org_designer.charts.publish') }}</button>
                                    @endif
                                    <button type="button" wire:click="confirmDelete({{ $chart->id }})" class="p-2 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-secondary-500" title="{{ __('common.actions.delete') }}">{{ __('common.actions.delete') }}</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">{{ __('org_designer.charts.empty') }}</td>
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
