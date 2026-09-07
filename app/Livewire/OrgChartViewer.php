<?php

namespace App\Livewire;

use App\Models\OrgProject;
use App\Support\OrgDesigner\OrgDesignerDocument;
use Livewire\Component;

class OrgChartViewer extends Component
{
    public OrgProject $project;

    public bool $readOnly = true;

    public function mount(OrgProject $orgProject): void
    {
        abort_unless($orgProject->isPublished(), 404);
        $this->project = $orgProject->loadMissing('user:id,name');
        $this->readOnly = true;
    }

    /**
     * @return array<string, mixed>
     */
    public function bootPayload(): array
    {
        $state = $this->project->state ?? OrgDesignerDocument::defaultState();
        $config = $this->project->config ?? OrgDesignerDocument::defaultConfig();

        return [
            'lockVersion' => (int) $this->project->lock_version,
            'state' => OrgDesignerDocument::encodeForClient($state),
            'config' => $config,
            'knownRoles' => OrgDesignerDocument::knownRoles(),
            'exportExcelUrl' => null,
            'chartName' => $this->project->name,
            'authorName' => $this->project->user?->name,
            'published' => true,
            'publicUrl' => route('org-charts.show', $this->project),
            'readOnly' => true,
            'i18n' => trans('org_designer.js'),
        ];
    }

    public function render()
    {
        return view('livewire.org-designer');
    }
}
