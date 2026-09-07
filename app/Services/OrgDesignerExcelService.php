<?php

namespace App\Services;

use App\Support\OrgDesigner\OrgDesignerDocument;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class OrgDesignerExcelService
{
    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $config
     * @return array{state: array<string, mixed>, config: array<string, mixed>, message: string, stats: array<string, int>}
     */
    public function import(string $path, array $state, array $config, string $mode): array
    {
        if (! in_array($mode, ['add', 'replace'], true)) {
            throw new InvalidArgumentException('Invalid import mode.');
        }

        $spreadsheet = IOFactory::load($path);
        $sheetName = (string) ($config['sheetName'] ?? 'New Baseline File - Option C');
        $worksheet = $spreadsheet->getSheetByName($sheetName);

        if ($worksheet === null) {
            foreach ($spreadsheet->getWorksheetIterator() as $candidate) {
                $title = strtolower($candidate->getTitle());
                if (str_contains($title, 'baseline') || str_contains($title, 'position')) {
                    $worksheet = $candidate;
                    $sheetName = $candidate->getTitle();
                    break;
                }
            }
        }

        if ($worksheet === null) {
            $names = $spreadsheet->getSheetNames();
            throw new InvalidArgumentException(__('org_designer.import.sheet_missing', [
                'sheet' => $sheetName,
                'available' => implode(', ', $names),
            ]));
        }

        $rows = $worksheet->toArray(null, true, true, false);
        $headerRowIdx = null;
        $limit = min(count($rows), 20);
        for ($i = 0; $i < $limit; $i++) {
            $cells = array_map(fn ($c) => $this->normalizeHeader((string) $c), $rows[$i] ?? []);
            if (in_array('teamid', $cells, true) && in_array('roletype', $cells, true)) {
                $headerRowIdx = $i;
                break;
            }
        }

        if ($headerRowIdx === null) {
            throw new InvalidArgumentException(__('org_designer.import.header_missing'));
        }

        $headers = array_map(fn ($c) => $this->normalizeHeader((string) $c), $rows[$headerRowIdx]);
        $dataRows = array_values(array_filter(
            array_slice($rows, $headerRowIdx + 1),
            fn ($row) => is_array($row) && collect($row)->contains(fn ($c) => trim((string) $c) !== '')
        ));

        $col = fn (string $name) => array_search($this->normalizeHeader($name), $headers, true);
        $c = [
            'teamId' => $col('Team_ID'),
            'teamName' => $col('Team Name'),
            'teamTopo' => $col('Team Topology'),
            'p1' => $col('Product 1'),
            'p2' => $col('Product 2'),
            'p3' => $col('Product 3'),
            'posId' => $col('Position_ID'),
            'role' => $col('Role Type'),
            'grade' => $col('Grade'),
            'fte' => $col('FTE'),
            'intExt' => $col('Internal / External'),
            'location' => $col('Location New'),
            'newSMT' => $col('New SMT Area'),
            'notes' => array_search('notes', $headers, true),
        ];

        $iltAreaGlobal = null;
        for ($i = 0; $i < $headerRowIdx; $i++) {
            $row = $rows[$i] ?? [];
            foreach ($row as $j => $value) {
                if (str_starts_with(strtolower(trim((string) $value)), 'ilt area')) {
                    $iltAreaGlobal = trim((string) ($row[$j + 1] ?? ''));
                    if ($iltAreaGlobal !== '') {
                        break 2;
                    }
                }
            }
        }
        if (! $iltAreaGlobal) {
            $iltAreaGlobal = 'Area';
        }

        $importedAreas = [];
        $teamsMap = [];

        foreach ($dataRows as $r) {
            $teamId = trim((string) ($c['teamId'] !== false ? $r[$c['teamId']] ?? '' : ''));
            if ($teamId === '') {
                continue;
            }
            $areaKey = ($c['newSMT'] !== false && trim((string) ($r[$c['newSMT']] ?? '')) !== '')
                ? trim((string) $r[$c['newSMT']])
                : $iltAreaGlobal;

            if (! isset($importedAreas[$areaKey])) {
                $importedAreas[$areaKey] = ['name' => $areaKey, 'head' => null, 'teams' => []];
            }

            $mapKey = $areaKey.'|'.$teamId;
            if (! isset($teamsMap[$mapKey])) {
                $team = [
                    'id' => $teamId,
                    'name' => trim((string) ($c['teamName'] !== false ? $r[$c['teamName']] ?? '' : '')),
                    'topology' => strtolower(trim((string) ($c['teamTopo'] !== false ? $r[$c['teamTopo']] ?? 'stream-aligned' : 'stream-aligned'))),
                    'products' => array_values(array_filter([
                        trim((string) ($c['p1'] !== false ? $r[$c['p1']] ?? '' : '')),
                        trim((string) ($c['p2'] !== false ? $r[$c['p2']] ?? '' : '')),
                        trim((string) ($c['p3'] !== false ? $r[$c['p3']] ?? '' : '')),
                    ])),
                    'notes' => '',
                    'positions' => [],
                ];
                $importedAreas[$areaKey]['teams'][] = $team;
                $teamsMap[$mapKey] = count($importedAreas[$areaKey]['teams']) - 1;
            }

            $teamIdx = $teamsMap[$mapKey];
            $importedAreas[$areaKey]['teams'][$teamIdx]['positions'][] = [
                'id' => trim((string) ($c['posId'] !== false && ($r[$c['posId']] ?? '') !== '' ? $r[$c['posId']] : 'Pos_'.bin2hex(random_bytes(4)))),
                'role' => trim((string) ($c['role'] !== false ? $r[$c['role']] ?? '' : '')),
                'grade' => trim((string) ($c['grade'] !== false ? $r[$c['grade']] ?? '' : '')),
                'fte' => trim((string) ($c['fte'] !== false && ($r[$c['fte']] ?? '') !== '' ? $r[$c['fte']] : '1')),
                'intExt' => str_starts_with(strtolower(trim((string) ($c['intExt'] !== false ? $r[$c['intExt']] ?? 'Internal' : 'Internal'))), 'ext') ? 'External' : 'Internal',
                'location' => $c['location'] !== false ? trim((string) ($r[$c['location']] ?? '')) : '',
                'notes' => $c['notes'] !== false ? trim((string) ($r[$c['notes']] ?? '')) : '',
            ];
        }

        $importedNames = array_keys($importedAreas);
        $stats = ['areas' => 0, 'teams_added' => 0, 'teams_updated' => 0, 'positions' => count($dataRows)];

        if ($mode === 'replace' || ($state['iltAreas'] ?? []) === []) {
            $state['iltAreas'] = $importedAreas;
            $state['bigPictureOrder'] = $importedNames;
            $state['bigPictureRows'] = [$importedNames];
            $state['currentILT'] = $importedNames[0] ?? null;
            $stats['areas'] = count($importedNames);
            $message = __('org_designer.import.replaced', [
                'areas' => count($importedNames),
                'positions' => count($dataRows),
            ]);
        } else {
            $areas = is_array($state['iltAreas'] ?? null) ? $state['iltAreas'] : [];
            $order = is_array($state['bigPictureOrder'] ?? null) ? $state['bigPictureOrder'] : [];
            $rowsOut = is_array($state['bigPictureRows'] ?? null) ? $state['bigPictureRows'] : [[]];
            if ($rowsOut === []) {
                $rowsOut = [[]];
            }

            foreach ($importedNames as $areaName) {
                $incoming = $importedAreas[$areaName];
                if (! isset($areas[$areaName])) {
                    $areas[$areaName] = $incoming;
                    $order[] = $areaName;
                    $rowsOut[count($rowsOut) - 1][] = $areaName;
                    $stats['areas']++;
                } else {
                    foreach ($incoming['teams'] as $newTeam) {
                        $idx = collect($areas[$areaName]['teams'])->search(fn ($t) => ($t['id'] ?? null) === $newTeam['id']);
                        if ($idx !== false) {
                            $areas[$areaName]['teams'][$idx] = $newTeam;
                            $stats['teams_updated']++;
                        } else {
                            $areas[$areaName]['teams'][] = $newTeam;
                            $stats['teams_added']++;
                        }
                    }
                }
            }

            $state['iltAreas'] = $areas;
            $state['bigPictureOrder'] = $order;
            $state['bigPictureRows'] = $rowsOut;
            if (! isset($state['currentILT']) || ! isset($areas[$state['currentILT']])) {
                $state['currentILT'] = array_key_first($areas);
            }
            $message = __('org_designer.import.added', [
                'areas' => $stats['areas'],
                'teams' => $stats['teams_added'],
                'updated' => $stats['teams_updated'],
            ]);
        }

        $config['sheetName'] = $sheetName;
        $state = OrgDesignerDocument::sanitizeState($state);
        $config = OrgDesignerDocument::sanitizeConfig($config);

        return ['state' => $state, 'config' => $config, 'message' => $message, 'stats' => $stats];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function exportToTempFile(array $state): string
    {
        $rows = [[
            'Team_ID', 'Team Name', 'Team Topology', 'No of Positions',
            'Product 1', 'Product 2', 'Product 3', 'Team Notes', 'Created_On',
            'Position_ID', '#', 'Role Type', 'Grade', 'FTE', 'Internal / External',
            'New SMT Area', 'Location New', 'Position Notes',
        ]];
        $now = now()->format('Y-m-d H:i:s');
        $areas = is_array($state['iltAreas'] ?? null) ? $state['iltAreas'] : [];

        foreach ($areas as $area) {
            foreach ($area['teams'] ?? [] as $team) {
                $positions = $team['positions'] ?? [];
                if ($positions === []) {
                    $rows[] = [
                        $team['id'] ?? '', $team['name'] ?? '', $team['topology'] ?? '', 0,
                        $team['products'][0] ?? '', $team['products'][1] ?? '', $team['products'][2] ?? '',
                        $team['notes'] ?? '', $now,
                        '', '', '', '', '', '', $area['name'] ?? '', '', '',
                    ];
                    continue;
                }
                foreach ($positions as $idx => $position) {
                    $rows[] = [
                        $team['id'] ?? '', $team['name'] ?? '', $team['topology'] ?? '', count($positions),
                        $team['products'][0] ?? '', $team['products'][1] ?? '', $team['products'][2] ?? '',
                        $team['notes'] ?? '', $now,
                        $position['id'] ?? '', $idx + 1, $position['role'] ?? '', $position['grade'] ?? '',
                        $position['fte'] ?? '', $position['intExt'] ?? '',
                        $area['name'] ?? '', $position['location'] ?? '', $position['notes'] ?? '',
                    ];
                }
            }
        }

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
        $spreadsheet->getActiveSheet()->setTitle('New Baseline File - Option C');

        $path = sys_get_temp_dir().'/org-designer-export-'.uniqid('', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function normalizeHeader(string $value): string
    {
        return strtolower(trim(preg_replace('/[\s_\/]+/', '', $value) ?? $value));
    }
}
