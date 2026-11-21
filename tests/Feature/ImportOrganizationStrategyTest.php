<?php

namespace Tests\Feature;

require_once __DIR__ . '/UiTestHarness.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Platform\Core\Contracts\ToolContext;
use Tests\TestCase;

/**
 * Import des Strategie-Layers der Organisation in ein Management Book (OrganizationStrategyImporter).
 * Aufruf: tests/run-in-taiste.sh tests/Feature/ImportOrganizationStrategyTest.php
 */
class ImportOrganizationStrategyTest extends TestCase
{
    use UiTestHarness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootModule();
        // Die Dimensions-Stammdaten der Organisation sind in sqlite unvollständig (Knoten-Link scheitert sonst am FK)
        DB::statement('PRAGMA foreign_keys = OFF');
    }

    private function row(string $table, array $data): int
    {
        $data += ['uuid' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()];

        return DB::table($table)->insertGetId(array_intersect_key($data, array_flip(\Illuminate\Support\Facades\Schema::getColumnListing($table))));
    }

    public function test_import_full_idempotent_dry_run_and_partial(): void
    {
        $U = \Platform\Core\Models\User::class;
        $user = $U::create(['name' => 'Martin', 'email' => 'm@x.test', 'password' => 'x']);
        $teamId = DB::table('teams')->insertGetId(['name' => 'T1', 'user_id' => $user->id, 'personal_team' => false, 'created_at' => now(), 'updated_at' => now()]);
        $team = \Platform\Core\Models\Team::find($teamId);
        DB::table('team_user')->insert(['team_id' => $teamId, 'user_id' => $user->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);
        $user->forceFill(['current_team_id' => $teamId])->save();
        $user = $user->fresh();
        $this->actingAs($user);

        $groupId = $this->row('organization_entity_type_groups', ['code' => 'business', 'name' => 'Business', 'team_id' => $teamId, 'is_active' => true, 'sort_order' => 1]);
        $typeId = $this->row('organization_entity_types', ['code' => 'venture', 'name' => 'Venture', 'team_id' => $teamId, 'is_active' => true, 'sort_order' => 1, 'entity_type_group_id' => $groupId]);
        $entityId = $this->row('organization_entities', ['name' => 'FOOD.WORKS', 'team_id' => $teamId, 'user_id' => $user->id, 'is_active' => true, 'entity_type_id' => $typeId]);

        $this->row('organization_strategic_documents', ['entity_id' => $entityId, 'team_id' => $teamId, 'type' => 'vision', 'title' => 'Professionelle Food-Entwicklung für alle', 'content' => "# Vision\n\nLanger Text.", 'version' => 2, 'is_active' => true, 'valid_from' => '2026-08-07']);
        $this->row('organization_strategic_documents', ['entity_id' => $entityId, 'team_id' => $teamId, 'type' => 'vision', 'title' => 'Alt', 'content' => 'alt', 'version' => 1, 'is_active' => false, 'valid_from' => '2026-07-01']);
        $this->row('organization_strategic_documents', ['entity_id' => $entityId, 'team_id' => $teamId, 'type' => 'mission', 'title' => 'Mission', 'content' => 'Warum es uns gibt.', 'version' => 1, 'is_active' => true, 'valid_from' => '2026-07-30']);

        $fc = $this->row('organization_forecasts', ['entity_id' => $entityId, 'team_id' => $teamId, 'title' => 'Zukunftsbild 2030', 'target_date' => '2030-12-31', 'content' => 'Regnose v2']);
        $this->row('organization_forecast_versions', ['forecast_id' => $fc, 'version' => 1, 'content' => 'Regnose v1', 'change_note' => 'Erstfassung']);
        $this->row('organization_forecast_versions', ['forecast_id' => $fc, 'version' => 2, 'content' => 'Regnose v2', 'change_note' => null]);

        $fa1 = $this->row('organization_focus_areas', ['entity_id' => $entityId, 'team_id' => $teamId, 'forecast_id' => $fc, 'title' => 'Leistungsmodell', 'description' => 'Leitsatz …', 'central_question_milestones' => 'Welche Etappen?', 'order' => 1]);
        $fa2 = $this->row('organization_focus_areas', ['entity_id' => $entityId, 'team_id' => $teamId, 'forecast_id' => null, 'title' => 'Kunden – Clients for Life', 'description' => 'ohne Forecast', 'order' => 2]);
        $this->row('organization_vision_images', ['entity_id' => $entityId, 'team_id' => $teamId, 'focus_area_id' => $fa1, 'title' => 'Zielbild A', 'description' => 'Beschreibung A', 'order' => 1]);
        $this->row('organization_obstacles', ['entity_id' => $entityId, 'team_id' => $teamId, 'focus_area_id' => $fa1, 'title' => 'Hindernis A', 'order' => 1]);
        $ms1 = $this->row('organization_milestones', ['entity_id' => $entityId, 'team_id' => $teamId, 'focus_area_id' => $fa1, 'title' => 'Meilenstein A', 'description' => 'Details', 'central_question' => 'Frage?', 'target_year' => 2027, 'target_quarter' => 2, 'order' => 1]);
        $this->row('organization_milestones', ['entity_id' => $entityId, 'team_id' => $teamId, 'focus_area_id' => $fa2, 'title' => 'Meilenstein ohne Forecast', 'target_year' => 2027, 'target_quarter' => 1, 'order' => 1]);
        $deleted = $this->row('organization_milestones', ['entity_id' => $entityId, 'team_id' => $teamId, 'focus_area_id' => $fa1, 'title' => 'Gelöscht', 'order' => 9, 'deleted_at' => now()]);
        $this->row('organization_milestone_contributions', ['milestone_id' => $ms1, 'team_id' => $teamId, 'linkable_type' => 'okr_objective', 'linkable_id' => 999999]);

        $importer = app(\Platform\ManagementBook\Services\OrganizationStrategyImporter::class);

        // Probelauf: zählt, speichert nichts
        $dry = $importer->import($entityId, $user, null, null, true);
        $this->assertTrue($dry['dry_run']);
        $this->assertSame(2, $dry['source']['focus_areas']);
        $this->assertSame(2, $dry['source']['milestones'], 'gelöschte Meilensteine zählen nicht');
        $this->assertSame(0, \Platform\ManagementBook\Models\ManagementBook::count());

        // Voller Import
        $r = $importer->import($entityId, $user);
        $this->assertSame(1, $r['created']['book']);
        $this->assertSame(2, $r['created']['documents'], 'nur aktive Mission/Vision');
        $this->assertSame(1, $r['created']['forecasts']);
        $this->assertSame(2, $r['created']['forecast_versions']);
        $this->assertSame(2, $r['created']['focus_areas'], 'auch Fokusraum ohne Forecast');
        $this->assertSame([1, 1, 2], [$r['created']['vision_images'], $r['created']['obstacles'], $r['created']['milestones']]);
        $this->assertSame(0, $r['created']['contributions']);
        $this->assertCount(1, $r['skipped'], 'nicht existierendes OKR-Objekt übersprungen');

        $book = \Platform\ManagementBook\Models\ManagementBook::findOrFail($r['book']['id']);
        $this->assertSame('Management Book FOOD.WORKS', $book->title);
        $this->assertSame('active', $book->status->value);
        $this->assertSame(2030, $book->horizon_year);
        $vision = \Platform\ManagementBook\Models\ManagementBookStrategicDocument::activeFor($book->id, \Platform\ManagementBook\Enums\StrategicDocumentType::VISION);
        $this->assertSame('Professionelle Food-Entwicklung für alle', $vision->title);
        $this->assertSame("# Vision\n\nLanger Text.", $vision->content);
        $forecast = \Platform\ManagementBook\Models\ManagementBookForecast::where('management_book_id', $book->id)->firstOrFail();
        $this->assertSame('Regnose v2', $forecast->content);
        $this->assertSame(2, $forecast->versions()->count());
        $this->assertSame('Regnose v1', $forecast->versions()->where('version', 1)->value('content'));
        $fa = \Platform\ManagementBook\Models\ManagementBookFocusArea::where('management_book_id', $book->id)->where('title', 'Leistungsmodell')->firstOrFail();
        $this->assertSame($forecast->id, $fa->forecast_id);
        $this->assertSame('Welche Etappen?', $fa->central_question_milestones);
        $this->assertSame(DB::table('organization_focus_areas')->where('id', $fa1)->value('uuid'), $fa->uuid, 'UUID der Quelle übernommen');
        $ms = \Platform\ManagementBook\Models\ManagementBookMilestone::where('title', 'Meilenstein A')->firstOrFail();
        $this->assertSame([2027, 2, 'Frage?', 'Details', 'planned'], [$ms->target_year, $ms->target_quarter, $ms->central_question, $ms->description, $ms->status->value]);
        $this->assertNull(\Platform\ManagementBook\Models\ManagementBookFocusArea::where('title', 'Kunden – Clients for Life')->value('forecast_id'));

        // Idempotent: erneuter Nachtrag in dasselbe Book legt nichts doppelt an
        $again = $importer->import($entityId, $user, $book);
        $this->assertSame(0, array_sum($again['created']));

        // Ohne Ziel-Book: zweites aktives Book am Knoten wird abgelehnt
        // (nur prüfbar, wenn die Knoten-Abfrage der Organisation in dieser Umgebung funktioniert)
        if (app(\Platform\ManagementBook\Services\ManagementBookService::class)->findActiveForNode($entityId)) {
            try {
                $importer->import($entityId, $user);
                $this->fail('zweites Book am Knoten muss abgelehnt werden');
            } catch (\Platform\ManagementBook\Exceptions\ManagementBookException $e) {
                $this->assertStringContainsString('bereits ein aktives Book', $e->getMessage());
            }
        }

        // Teil-Nachtrag in ein anderes Book (nur Fokusraum ohne Forecast)
        $other = app(\Platform\ManagementBook\Services\ManagementBookService::class)->create($user, $team, ['title' => 'Anderes Book']);
        DB::table('management_book_milestones')->delete();
        DB::table('management_book_focus_areas')->delete();
        $part = $importer->import($entityId, $user, $other, [$fa2]);
        $this->assertSame([0, 0, 1, 1], [$part['created']['documents'], $part['created']['forecasts'], $part['created']['focus_areas'], $part['created']['milestones']]);
        $this->assertSame($other->id, \Platform\ManagementBook\Models\ManagementBookFocusArea::where('title', 'Kunden – Clients for Life')->value('management_book_id'));

        // Tool: Rechte + Antwort
        $tool = new \Platform\ManagementBook\Tools\ImportOrganizationStrategyTool();
        $res = $tool->execute(['entity_id' => $entityId, 'dry_run' => true], new ToolContext($user, $team));
        $this->assertFalse($res->success, 'ohne Schreibrecht auf den Knoten (kein Org-Graph im Test) abgelehnt');
    }
}
