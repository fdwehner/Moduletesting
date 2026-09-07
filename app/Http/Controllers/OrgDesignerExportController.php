<?php

namespace App\Http\Controllers;

use App\Models\OrgProject;
use App\Support\OrgDesigner\OrgDesignerDocument;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrgDesignerExportController extends Controller
{
    use LogsActivity;

    public function excel(Request $request): StreamedResponse
    {
        $project = OrgProject::firstOrCreateForUser($request->user());
        $this->authorize('export', $project);

        $path = app(\App\Services\OrgDesignerExcelService::class)
            ->exportToTempFile($project->state ?? OrgDesignerDocument::defaultState());

        $this->logCrud('viewed', $project, ['action' => 'excel_export']);

        $filename = now()->format('ymd').'_GIT_Big_Picture.xlsx';

        return response()->streamDownload(function () use ($path) {
            readfile($path);
            @unlink($path);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function json(Request $request): StreamedResponse
    {
        $project = OrgProject::firstOrCreateForUser($request->user());
        $this->authorize('export', $project);

        $payload = json_encode([
            'version' => 9,
            'savedAt' => now()->toIso8601String(),
            'state' => $project->state ?? OrgDesignerDocument::defaultState(),
            'CONFIG' => $project->config ?? OrgDesignerDocument::defaultConfig(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $this->logCrud('viewed', $project, ['action' => 'json_export']);

        $area = $project->state['currentILT'] ?? 'project';
        $safe = preg_replace('/[^a-z0-9]+/i', '_', (string) $area) ?: 'project';
        $filename = 'OrgDesigner_'.$safe.'_'.now()->toDateString().'.json';

        return response()->streamDownload(function () use ($payload) {
            echo $payload;
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
}
