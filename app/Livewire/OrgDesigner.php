<?php

namespace App\Livewire;

use App\Models\OrgProject;
use App\Services\FormValidationService;
use App\Services\OrgDesignerExcelService;
use App\Support\OrgDesigner\OrgDesignerDocument;
use App\Traits\LogsActivity;
use App\Traits\WithToastNotifications;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class OrgDesigner extends Component
{
    use AuthorizesRequests;
    use LogsActivity;
    use WithFileUploads;
    use WithToastNotifications;

    public OrgProject $project;

    public int $lockVersion = 1;

    public mixed $excelFile = null;

    public string $importMode = 'add';

    public string $chartName = '';

    public bool $readOnly = false;

    public function mount(OrgProject $orgProject): void
    {
        $this->authorize('update', $orgProject);
        $this->project = $orgProject;
        $this->chartName = $orgProject->name;
        $this->lockVersion = (int) $this->project->lock_version;
        $this->readOnly = false;
    }

    public function updatedExcelFile(): void
    {
        $this->skipRender();
        $this->authorize('import', $this->project);
        $areas = $this->project->state['iltAreas'] ?? [];
        if (is_array($areas) && count($areas) > 0) {
            $this->dispatch('org-designer-ask-import-mode', areaCount: count($areas));

            return;
        }
        $this->importMode = 'replace';
        $this->importExcel();
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function persist(array $state, array $config, int $lockVersion): array
    {
        $this->skipRender();
        $this->authorize('update', $this->project);
        $this->project->refresh();

        if ((int) $this->project->lock_version !== $lockVersion) {
            return [
                'ok' => false,
                'conflict' => true,
                'message' => __('org_designer.messages.conflict'),
                'lockVersion' => (int) $this->project->lock_version,
                'state' => OrgDesignerDocument::encodeForClient($this->project->state ?? OrgDesignerDocument::defaultState()),
                'config' => $this->project->config ?? OrgDesignerDocument::defaultConfig(),
            ];
        }

        $this->project->state = OrgDesignerDocument::sanitizeState($state);
        $this->project->config = OrgDesignerDocument::sanitizeConfig($config);
        $this->project->lock_version = $lockVersion + 1;
        $this->project->save();
        $this->lockVersion = (int) $this->project->lock_version;

        $this->logCrud('updated', $this->project, ['action' => 'persist']);

        return [
            'ok' => true,
            'conflict' => false,
            'lockVersion' => $this->lockVersion,
            'savedAt' => $this->project->state['savedAt'] ?? now()->toIso8601String(),
            'published' => $this->project->isPublished(),
            'publicUrl' => $this->project->isPublished() ? route('org-charts.show', $this->project) : null,
        ];
    }

    public function renameChart(string $name): void
    {
        $this->skipRender();
        $this->authorize('update', $this->project);
        $this->chartName = $name;
        $validator = app(FormValidationService::class);
        $this->validate(
            $this->mapChartNameRules($validator->getValidationRules('org_chart_name')),
            $this->mapChartNameRules($validator->getValidationMessages('org_chart_name')),
        );
        $this->project->refresh();
        $this->project->name = trim($name);
        $this->project->save();
        $this->chartName = $this->project->name;
        $this->logCrud('updated', $this->project, ['action' => 'rename']);
        $this->toastSuccess(__('org_designer.charts.renamed'));
        $this->dispatchDesignerReload();
    }

    public function publish(): void
    {
        $this->skipRender();
        $this->authorize('publish', $this->project);
        $this->project->refresh();
        $this->project->published_at = now();
        $this->project->save();
        $this->logCrud('updated', $this->project, ['action' => 'publish']);
        $this->toastSuccess(__('org_designer.charts.published'));
        $this->dispatchDesignerReload();
    }

    public function unpublish(): void
    {
        $this->skipRender();
        $this->authorize('publish', $this->project);
        $this->project->refresh();
        $this->project->published_at = null;
        $this->project->save();
        $this->logCrud('updated', $this->project, ['action' => 'unpublish']);
        $this->toastSuccess(__('org_designer.charts.unpublished'));
        $this->dispatchDesignerReload();
    }

    public function importExcel(): void
    {
        $this->skipRender();
        $this->authorize('import', $this->project);

        try {
            $this->validate(
                app(FormValidationService::class)->getValidationRules('org_designer_excel')
            );
        } catch (ValidationException $exception) {
            $this->toastError($exception->validator->errors()->first() ?: __('common.messages.error'));

            return;
        }

        /** @var TemporaryUploadedFile $file */
        $file = $this->excelFile;
        $mode = $this->importMode === 'replace' ? 'replace' : 'add';

        try {
            $result = app(OrgDesignerExcelService::class)->import(
                $file->getRealPath(),
                $this->project->state ?? OrgDesignerDocument::defaultState(),
                $this->project->config ?? OrgDesignerDocument::defaultConfig(),
                $mode,
            );
            $this->project->refresh();
            $this->project->state = $result['state'];
            $this->project->config = $result['config'];
            $this->project->lock_version = (int) $this->project->lock_version + 1;
            $this->project->save();
            $this->lockVersion = (int) $this->project->lock_version;
            $this->excelFile = null;
            $this->logCrud('updated', $this->project, ['action' => 'excel_import', 'mode' => $mode]);
            $this->toastSuccess($result['message']);
            $this->dispatchDesignerReload();
        } catch (InvalidArgumentException $exception) {
            $this->toastError($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->logError('Org designer Excel import failed', ['error' => $exception->getMessage()]);
            $this->toastError(__('common.messages.error'));
        }
    }

    public function resetProject(): void
    {
        $this->skipRender();
        $this->authorize('update', $this->project);
        $this->project->refresh();
        $this->project->state = OrgDesignerDocument::defaultState();
        $this->project->config = OrgDesignerDocument::defaultConfig();
        $this->project->lock_version = (int) $this->project->lock_version + 1;
        $this->project->save();
        $this->lockVersion = (int) $this->project->lock_version;
        $this->logCrud('updated', $this->project, ['action' => 'reset']);
        $this->toastSuccess(__('org_designer.messages.cleared'));
        $this->dispatchDesignerReload();
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
            'exportExcelUrl' => $this->readOnly ? null : route('org-designer.export.excel', $this->project),
            'chartName' => $this->project->name,
            'authorName' => $this->project->user?->name,
            'published' => $this->project->isPublished(),
            'publicUrl' => $this->project->isPublished() ? route('org-charts.show', $this->project) : null,
            'readOnly' => $this->readOnly,
            'i18n' => trans('org_designer.js'),
        ];
    }

    public function render()
    {
        return view('livewire.org-designer');
    }

    private function dispatchDesignerReload(): void
    {
        $this->dispatch('org-designer-reloaded', payload: $this->bootPayload());
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function mapChartNameRules(array $rules): array
    {
        $mapped = [];
        foreach ($rules as $key => $value) {
            $mapped[(string) preg_replace('/^name\b/', 'chartName', (string) $key)] = $value;
        }

        return $mapped;
    }
}
