<?php

namespace App\Http\Controllers;

use App\Models\OrgProject;
use App\Services\OrgDesignerExcelService;
use App\Support\OrgDesigner\OrgDesignerDocument;
use App\Traits\LogsActivity;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrgDesignerExportController extends Controller
{
    use LogsActivity;

    public function excel(OrgProject $orgProject): StreamedResponse
    {
        $this->authorize('export', $orgProject);

        $path = app(OrgDesignerExcelService::class)
            ->exportToTempFile($orgProject->state ?? OrgDesignerDocument::defaultState());

        $this->logCrud('viewed', $orgProject, ['action' => 'excel_export']);

        $safe = preg_replace('/[^a-z0-9]+/i', '_', $orgProject->name) ?: 'org-chart';
        $filename = now()->format('ymd').'_'.$safe.'.xlsx';

        return response()->streamDownload(function () use ($path) {
            readfile($path);
            @unlink($path);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
