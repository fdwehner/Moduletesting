<?php

namespace App\Livewire;

use App\Models\OrgProject;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PublishedOrgChartsIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public bool $filtersOpen = false;

    public function mount(): void
    {
        $this->filtersOpen = request()->filled('search');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function render()
    {
        $charts = OrgProject::query()
            ->published()
            ->with('user:id,name')
            ->when($this->search !== '', function ($query) {
                $query->where(function ($inner) {
                    $inner->where('name', 'like', '%'.$this->search.'%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->latest('published_at')
            ->paginate(15);

        return view('livewire.published-org-charts-index', [
            'charts' => $charts,
            'hasActiveFilters' => $this->search !== '',
        ]);
    }
}
