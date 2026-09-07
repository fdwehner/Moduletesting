<?php

namespace App\Livewire;

use App\Models\OrgProject;
use App\Traits\LogsActivity;
use App\Traits\WithToastNotifications;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class OrgChartsIndex extends Component
{
    use AuthorizesRequests;
    use LogsActivity;
    use WithPagination;
    use WithToastNotifications;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    public bool $filtersOpen = false;

    public function mount(): void
    {
        $this->authorize('viewAny', OrgProject::class);
        $this->filtersOpen = request()->anyFilled(['search', 'status']);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->resetPage();
    }

    public function createChart(): void
    {
        $this->authorize('create', OrgProject::class);
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $chart = OrgProject::createForUser($user, __('org_designer.charts.untitled'));
        $this->logCrud('created', $chart);

        $this->redirect(route('org-designer.edit', $chart), navigate: true);
    }

    public function confirmDelete(int $id): void
    {
        $chart = $this->ownedChart($id);
        $this->authorize('delete', $chart);

        $this->dispatch('open-confirmation',
            title: __('org_designer.charts.delete_title'),
            message: __('org_designer.charts.delete_confirm', ['name' => $chart->name]),
            confirmEvent: 'delete-org-chart',
            payload: $chart->id,
        );
    }

    #[On('delete-org-chart')]
    public function deleteChart(int $id): void
    {
        $chart = $this->ownedChart($id);
        $this->authorize('delete', $chart);
        $chart->delete();
        $this->logCrud('deleted', $chart);
        $this->toastSuccess(__('org_designer.charts.deleted'));
    }

    public function publish(int $id): void
    {
        $chart = $this->ownedChart($id);
        $this->authorize('publish', $chart);
        $chart->published_at = now();
        $chart->save();
        $this->logCrud('updated', $chart, ['action' => 'publish']);
        $this->toastSuccess(__('org_designer.charts.published'));
    }

    public function unpublish(int $id): void
    {
        $chart = $this->ownedChart($id);
        $this->authorize('publish', $chart);
        $chart->published_at = null;
        $chart->save();
        $this->logCrud('updated', $chart, ['action' => 'unpublish']);
        $this->toastSuccess(__('org_designer.charts.unpublished'));
    }

    public function render()
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        $base = OrgProject::query()->forUser($user);

        $stats = [
            'total' => (clone $base)->count(),
            'published' => (clone $base)->published()->count(),
            'draft' => (clone $base)->whereNull('published_at')->count(),
        ];

        $charts = (clone $base)
            ->when($this->search !== '', function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%');
            })
            ->when($this->status === 'published', fn ($query) => $query->published())
            ->when($this->status === 'draft', fn ($query) => $query->whereNull('published_at'))
            ->latest('updated_at')
            ->paginate(15);

        return view('livewire.org-charts-index', [
            'charts' => $charts,
            'stats' => $stats,
            'hasActiveFilters' => $this->search !== '' || $this->status !== '',
        ]);
    }

    private function ownedChart(int $id): OrgProject
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        return OrgProject::query()->forUser($user)->findOrFail($id);
    }
}
