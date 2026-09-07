<?php

namespace Tests\Feature\OrgDesigner;

use App\Livewire\OrgChartsIndex;
use App\Livewire\OrgDesigner;
use App\Models\OrgProject;
use App\Models\User;
use App\Services\OrgDesignerExcelService;
use App\Support\OrgDesigner\OrgDesignerDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class OrgDesignerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_org_designer_index(): void
    {
        $this->get(route('org-designer.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_see_their_chart_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('org-designer.index'))
            ->assertOk()
            ->assertSee(__('org_designer.charts.title'), false)
            ->assertSee(__('org_designer.charts.empty'), false);

        $this->assertDatabaseMissing('org_projects', ['user_id' => $user->id]);
    }

    public function test_users_can_create_multiple_charts_in_their_account(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(OrgChartsIndex::class)
            ->call('createChart')
            ->assertRedirect();

        Livewire::actingAs($user)
            ->test(OrgChartsIndex::class)
            ->call('createChart')
            ->assertRedirect();

        $this->assertSame(2, OrgProject::query()->where('user_id', $user->id)->count());
    }

    public function test_users_persist_only_their_own_org_chart(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownerChart = OrgProject::createForUser($owner, 'Owner chart');
        $otherChart = OrgProject::createForUser($other, 'Other chart');

        Livewire::actingAs($owner)
            ->test(OrgDesigner::class, ['orgProject' => $ownerChart])
            ->call('persist', $this->sampleState('HRIT'), OrgDesignerDocument::defaultConfig(), 1)
            ->assertHasNoErrors();

        $ownerChart->refresh();
        $this->assertSame('HRIT', $ownerChart->state['currentILT'] ?? null);

        $this->actingAs($other)
            ->get(route('org-designer.edit', $ownerChart))
            ->assertForbidden();

        $otherChart->refresh();
        $this->assertSame([], $otherChart->state['iltAreas'] ?? ['x']);
    }

    public function test_excel_import_creates_teams_and_positions(): void
    {
        $user = User::factory()->create();
        $chart = OrgProject::createForUser($user, 'Baseline');
        $path = $this->writeBaselineSpreadsheet();

        $imported = app(OrgDesignerExcelService::class)->import(
            $path,
            OrgDesignerDocument::defaultState(),
            OrgDesignerDocument::defaultConfig(),
            'replace',
        );

        Livewire::actingAs($user)
            ->test(OrgDesigner::class, ['orgProject' => $chart])
            ->call('persist', $imported['state'], $imported['config'], 1)
            ->assertHasNoErrors();

        $chart->refresh();
        $this->assertSame('Platform Team', $chart->state['iltAreas']['HRIT']['teams'][0]['name'] ?? null);

        @unlink($path);
    }

    public function test_persist_rejects_a_stale_lock_version(): void
    {
        $user = User::factory()->create();
        $project = OrgProject::createForUser($user, 'Locked');
        $project->update(['lock_version' => 5]);

        Livewire::actingAs($user)
            ->test(OrgDesigner::class, ['orgProject' => $project])
            ->call('persist', $this->sampleState('HRIT'), OrgDesignerDocument::defaultConfig(), 1)
            ->assertHasNoErrors();

        $project->refresh();
        $this->assertSame(5, $project->lock_version);
        $this->assertSame([], $project->state['iltAreas'] ?? ['x']);
    }

    public function test_persist_strips_html_from_team_names(): void
    {
        $user = User::factory()->create();
        $project = OrgProject::createForUser($user, 'Sanitize');
        $state = $this->sampleState('HRIT');
        $state['iltAreas']['HRIT']['teams'][0]['name'] = '<b>Platform</b>';

        Livewire::actingAs($user)
            ->test(OrgDesigner::class, ['orgProject' => $project])
            ->call('persist', $state, OrgDesignerDocument::defaultConfig(), 1)
            ->assertHasNoErrors();

        $project->refresh();
        $this->assertSame('Platform', $project->state['iltAreas']['HRIT']['teams'][0]['name'] ?? null);
    }

    public function test_json_export_route_is_removed(): void
    {
        $this->assertFalse(Route::has('org-designer.export.json'));
    }

    public function test_index_lists_only_the_owners_charts(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        OrgProject::createForUser($owner, 'Mine only');
        OrgProject::createForUser($other, 'Someone else');

        $this->actingAs($owner)
            ->get(route('org-designer.index'))
            ->assertOk()
            ->assertSee('Mine only', false)
            ->assertDontSee('Someone else', false);
    }

    public function test_published_charts_appear_on_the_home_page(): void
    {
        $owner = User::factory()->create(['name' => 'Ada']);
        $chart = OrgProject::createForUser($owner, 'Home GIT');
        $chart->update(['published_at' => now()]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Home GIT', false)
            ->assertSee('Ada', false);
    }

    public function test_other_users_cannot_mount_the_editor(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $chart = OrgProject::createForUser($owner, 'Private');

        Livewire::actingAs($intruder)
            ->test(OrgDesigner::class, ['orgProject' => $chart])
            ->assertForbidden();
    }

    public function test_owner_can_publish_and_guests_can_view_the_public_chart(): void
    {
        $owner = User::factory()->create(['name' => 'Ada']);
        $chart = OrgProject::createForUser($owner, 'Public GIT');

        Livewire::actingAs($owner)
            ->test(OrgDesigner::class, ['orgProject' => $chart])
            ->call('publish')
            ->assertHasNoErrors();

        $chart->refresh();
        $this->assertNotNull($chart->published_at);

        $this->get(route('org-charts.show', $chart))
            ->assertOk()
            ->assertSee('Public GIT', false)
            ->assertSee('Ada', false);

        $this->get(route('org-charts.public-index'))
            ->assertOk()
            ->assertSee('Public GIT', false);
    }

    public function test_unpublished_charts_are_not_public(): void
    {
        $owner = User::factory()->create();
        $chart = OrgProject::createForUser($owner, 'Secret');

        $this->get(route('org-charts.show', $chart))->assertNotFound();
        $this->get(route('org-charts.public-index'))->assertOk()->assertDontSee('Secret', false);
    }

    public function test_other_users_cannot_update_a_project_via_policy(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = OrgProject::createForUser($owner, 'Owned');

        $this->assertFalse($intruder->can('update', $project));
        $this->assertTrue($owner->can('update', $project));
        $this->assertTrue($owner->can('publish', $project));
        $this->assertFalse($intruder->can('view', $project));
    }

    public function test_users_can_delete_their_chart_from_the_index(): void
    {
        $user = User::factory()->create();
        $chart = OrgProject::createForUser($user, 'Disposable');

        Livewire::actingAs($user)
            ->test(OrgChartsIndex::class)
            ->call('deleteChart', $chart->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('org_projects', ['id' => $chart->id]);
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
