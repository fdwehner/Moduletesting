<?php

namespace Tests\Feature\OrgDesigner;

use App\Livewire\OrgDesigner;
use App\Models\OrgProject;
use App\Models\User;
use App\Services\OrgDesignerExcelService;
use App\Support\OrgDesigner\OrgDesignerDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class OrgDesignerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_org_designer(): void
    {
        $this->get(route('org-designer.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_the_org_designer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('org-designer.index'))
            ->assertOk()
            ->assertSee(__('org_designer.title'), false)
            ->assertSee(__('org_designer.welcome_body'), false);

        $this->assertDatabaseHas('org_projects', ['user_id' => $user->id]);
    }

    public function test_users_persist_only_their_own_org_chart(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $state = $this->sampleState('HRIT');

        Livewire::actingAs($owner)
            ->test(OrgDesigner::class)
            ->call('persist', $state, OrgDesignerDocument::defaultConfig(), 1)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('org_projects', ['user_id' => $owner->id]);
        $ownerProject = OrgProject::query()->where('user_id', $owner->id)->first();
        $this->assertSame('HRIT', $ownerProject?->state['currentILT'] ?? null);

        Livewire::actingAs($other)
            ->test(OrgDesigner::class)
            ->assertSuccessful();

        $otherProject = OrgProject::query()->where('user_id', $other->id)->first();
        $this->assertNotSame($ownerProject?->id, $otherProject?->id);
        $this->assertSame([], $otherProject?->state['iltAreas'] ?? ['x']);
    }

    public function test_excel_import_creates_teams_and_positions(): void
    {
        $user = User::factory()->create();
        $path = $this->writeBaselineSpreadsheet();

        $imported = app(OrgDesignerExcelService::class)->import(
            $path,
            OrgDesignerDocument::defaultState(),
            OrgDesignerDocument::defaultConfig(),
            'replace',
        );

        $this->assertArrayHasKey('HRIT', $imported['state']['iltAreas']);
        $this->assertSame('Platform Team', $imported['state']['iltAreas']['HRIT']['teams'][0]['name']);
        $this->assertSame('Team Lead', $imported['state']['iltAreas']['HRIT']['teams'][0]['positions'][0]['role']);

        Livewire::actingAs($user)
            ->test(OrgDesigner::class)
            ->call('persist', $imported['state'], $imported['config'], 1)
            ->assertHasNoErrors();

        $project = OrgProject::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($project);
        $this->assertArrayHasKey('HRIT', $project->state['iltAreas'] ?? []);
        $this->assertSame('Platform Team', $project->state['iltAreas']['HRIT']['teams'][0]['name'] ?? null);
        $this->assertSame('Team Lead', $project->state['iltAreas']['HRIT']['teams'][0]['positions'][0]['role'] ?? null);

        @unlink($path);
    }

    public function test_persist_rejects_a_stale_lock_version(): void
    {
        $user = User::factory()->create();
        $project = OrgProject::firstOrCreateForUser($user);
        $project->update(['lock_version' => 5]);

        Livewire::actingAs($user)
            ->test(OrgDesigner::class)
            ->call('persist', $this->sampleState('HRIT'), OrgDesignerDocument::defaultConfig(), 1)
            ->assertHasNoErrors();

        $project->refresh();
        $this->assertSame(5, $project->lock_version);
        $this->assertSame([], $project->state['iltAreas'] ?? ['x']);
    }

    public function test_persist_strips_html_from_team_names(): void
    {
        $user = User::factory()->create();
        $state = $this->sampleState('HRIT');
        $state['iltAreas']['HRIT']['teams'][0]['name'] = '<b>Platform</b>';

        Livewire::actingAs($user)
            ->test(OrgDesigner::class)
            ->call('persist', $state, OrgDesignerDocument::defaultConfig(), 1)
            ->assertHasNoErrors();

        $project = OrgProject::query()->where('user_id', $user->id)->first();
        $this->assertSame('Platform', $project->state['iltAreas']['HRIT']['teams'][0]['name'] ?? null);
    }

    public function test_users_can_export_their_own_json_project(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('org-designer.export.json'))
            ->assertOk();
    }

    public function test_other_users_cannot_update_a_project_via_policy(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = OrgProject::firstOrCreateForUser($owner);

        $this->assertFalse($intruder->can('update', $project));
        $this->assertTrue($owner->can('update', $project));
        $this->assertTrue($owner->can('export', $project));
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleState(string $area): array
    {
        return [
            'iltAreas' => [
                $area => [
                    'name' => $area,
                    'head' => null,
                    'teams' => [[
                        'id' => 'T1',
                        'name' => 'Platform',
                        'topology' => 'platform',
                        'products' => ['Core'],
                        'notes' => '',
                        'positions' => [[
                            'id' => 'P1',
                            'role' => 'Team Lead',
                            'grade' => '10',
                            'fte' => '1',
                            'intExt' => 'Internal',
                            'location' => 'KL',
                            'notes' => '',
                        ]],
                    ]],
                ],
            ],
            'currentILT' => $area,
            'viewMode' => 'team',
            'bigPictureOrder' => [$area],
            'bigPictureRows' => [[$area]],
            'zoom' => 1,
        ];
    }

    private function writeBaselineSpreadsheet(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('New Baseline File - Option C');
        $sheet->setCellValue('A1', 'ILT Area');
        $sheet->setCellValue('B1', 'HRIT');
        $sheet->fromArray([
            'Team_ID', 'Team Name', 'Team Topology', 'Product 1', 'Product 2', 'Product 3',
            'Position_ID', 'Role Type', 'Grade', 'FTE', 'Internal / External', 'Location New', 'New SMT Area',
        ], null, 'A3');
        $sheet->fromArray([
            'T1', 'Platform Team', 'platform', 'Core', '', '',
            'P1', 'Team Lead', '10', '1', 'Internal', 'KL', 'HRIT',
        ], null, 'A4');

        $path = sys_get_temp_dir().'/baseline-'.uniqid('', true).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
