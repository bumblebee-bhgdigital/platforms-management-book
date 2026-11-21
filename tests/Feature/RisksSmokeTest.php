<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Platform\Core\Contracts\ToolContext;
use Tests\TestCase;

/**
 * Smoke-Test für #1054 (Risiken + Risikobereitschaft). Läuft NUR in einer Verifikations-App (vendor/martin3r/*) mit
 * isolierter sqlite-:memory:-DB. Aufruf: Datei nach <app>/tests/Feature kopieren, dann
 * MB_MODULE_PATH=<pfad-zum-modul> DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/phpunit --filter RisksSmokeTest
 */
class RisksSmokeTest extends TestCase
{
    private string $module;

    protected function setUp(): void
    {
        parent::setUp();
        // SICHERHEIT: nur isolierte sqlite-Memory-DB zulassen.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        $this->module = getenv('MB_MODULE_PATH') ?: '/home/agent/agent-workspace/platforms-management-book';
        $module = $this->module;
        spl_autoload_register(function ($c) use ($module) {
            $p = 'Platform\\ManagementBook\\';
            if (str_starts_with($c, $p)) {
                $f = $module . '/src/' . str_replace('\\', '/', substr($c, strlen($p))) . '.php';
                if (is_file($f)) {
                    require $f;
                }
            }
        });
        $files = array_merge(
            glob(base_path('vendor/martin3r/platform-core/database/migrations/*.php')),
            glob(base_path('vendor/martin3r/platform-organization/database/migrations/*.php')),
            glob($module . '/database/migrations/*.php'),
        );
        usort($files, fn ($a, $b) => strcmp(basename($a), basename($b)));
        foreach ($files as $f) {
            try {
                (require $f)->up();
            } catch (\Throwable $e) {
                // Fremd-Migrationen, die auf sqlite nicht laufen, ignorieren
            }
        }
        $this->app->register(\Platform\ManagementBook\ManagementBookServiceProvider::class);
    }

    public function test_migration_up_down(): void
    {
        $this->assertTrue(Schema::hasTable('management_book_risks'));
        $this->assertTrue(Schema::hasColumn('management_books', 'risk_appetite'));
        $m = require $this->module . '/database/migrations/2026_11_21_000001_create_management_book_risks_table.php';
        $m->up(); // idempotent
        $m->down();
        $this->assertFalse(Schema::hasTable('management_book_risks'));
        $this->assertFalse(Schema::hasColumn('management_books', 'risk_appetite'));
        $this->assertTrue(Schema::hasTable('management_books'), 'Eltern bleiben');
        $m->up();
        $this->assertTrue(Schema::hasTable('management_book_risks'));
        $this->assertTrue(Schema::hasColumn('management_books', 'risk_appetite'));
    }

    public function test_levels(): void
    {
        $L = \Platform\ManagementBook\Enums\RiskLevel::class;
        $this->assertNull($L::fromScore(null));
        $this->assertSame('low', $L::fromScore(1)->value);
        $this->assertSame('low', $L::fromScore(4)->value);
        $this->assertSame('moderate', $L::fromScore(5)->value);
        $this->assertSame('moderate', $L::fromScore(9)->value);
        $this->assertSame('high', $L::fromScore(10)->value);
        $this->assertSame('high', $L::fromScore(15)->value);
        $this->assertSame('critical', $L::fromScore(16)->value);
        $this->assertSame('critical', $L::fromScore(25)->value);
    }

    public function test_crud_and_tools(): void
    {
        $U = \Platform\Core\Models\User::class;
        $user = $U::create(['name' => 'U', 'email' => 'u@x.test', 'password' => 'x']);
        $agent = $U::create(['name' => 'A', 'email' => 'a@x.test', 'password' => 'x']);
        $teamId = DB::table('teams')->insertGetId(['name' => 'T1', 'user_id' => $user->id, 'personal_team' => false, 'created_at' => now(), 'updated_at' => now()]);
        $team = \Platform\Core\Models\Team::find($teamId);
        DB::table('team_user')->insert(['team_id' => $teamId, 'user_id' => $user->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);
        $user->forceFill(['current_team_id' => $teamId])->save();
        $user = $user->fresh();
        $this->actingAs($user);

        $book = app(\Platform\ManagementBook\Services\ManagementBookService::class)->create($user, $team, ['title' => 'Book']);
        $entityId = null;
        try {
            $entityId = \Platform\Organization\Models\OrganizationEntity::create(['name' => 'Einkauf/Logistik', 'team_id' => $teamId, 'user_id' => $user->id])->id;
        } catch (\Throwable $e) {
            // Entity-Tabelle verlangt in dieser App weitere Pflichtfelder -> Knoten-Test entfällt
        }

        // Model: Score und Ampel
        $risk = $book->risks()->create(['team_id' => $teamId, 'title' => 'Lieferengpass', 'probability' => 4, 'impact' => 3]);
        $this->assertSame(12, $risk->fresh()->score);
        $this->assertSame('high', $risk->fresh()->level()->value);
        $this->assertSame('open', $risk->fresh()->status->value);
        $this->assertNotEmpty($risk->uuid);
        $risk->update(['impact' => null]);
        $this->assertNull($risk->fresh()->score, 'ohne A kein Score');

        // Policies (erben vom Book)
        $this->assertTrue(Gate::forUser($user)->allows('update', $risk));
        $other = $U::create(['name' => 'O', 'email' => 'o@x.test', 'password' => 'x']);
        $this->assertFalse(Gate::forUser($other)->allows('view', $risk));
        $this->assertFalse(Gate::forUser($other)->allows('update', $risk));

        // Tools
        $ctx = new ToolContext($user, $team);
        $ns = '\\Platform\\ManagementBook\\Tools\\';
        $run = function (string $cls, array $args) use ($ns, $ctx) {
            $c = $ns . $cls;

            return (new $c())->execute($args, $ctx);
        };

        $r = $run('CreateRiskTool', array_filter([
            'management_book_id' => $book->id, 'number' => 'R-001', 'title' => 'Lieferengpass bei Frischeprodukten',
            'organization_entity_id' => $entityId, 'probability' => 4, 'impact' => 3, 'category' => 'operatives Risiko',
            'measures_planned' => 'alternative Lieferanten prüfen', 'owner_user_id' => $user->id, 'due_date' => '2027-03-31',
        ]));
        $this->assertTrue($r->success, json_encode($r));
        $this->assertSame(12, $r->data['risk']['score']);
        $this->assertSame('high', $r->data['risk']['level']);
        $this->assertSame('orange', $r->data['risk']['level_color']);
        $this->assertSame('2027-03-31', $r->data['risk']['due_date']);
        if ($entityId) {
            $this->assertSame('Einkauf/Logistik', $r->data['risk']['entity_name']);
        }
        $rid = $r->data['risk']['id'];

        $r = $run('CreateRiskTool', ['management_book_id' => $book->id, 'title' => 'Cyberangriff', 'probability' => 5, 'impact' => 5, 'owner_agent_id' => $agent->id]);
        $this->assertTrue($r->success, json_encode($r));
        $this->assertSame('critical', $r->data['risk']['level']);
        $critId = $r->data['risk']['id'];

        // Validierung
        $this->assertFalse($run('CreateRiskTool', ['management_book_id' => $book->id, 'title' => 'x', 'probability' => 6])->success);
        $this->assertFalse($run('CreateRiskTool', ['management_book_id' => $book->id, 'title' => 'x', 'impact' => 0])->success);
        $this->assertFalse($run('CreateRiskTool', ['management_book_id' => $book->id, 'title' => 'x', 'probability' => 2.5])->success);
        $this->assertFalse($run('CreateRiskTool', ['management_book_id' => $book->id, 'title' => 'x', 'status' => 'bogus'])->success);
        $this->assertFalse($run('CreateRiskTool', ['management_book_id' => $book->id, 'title' => 'x', 'owner_user_id' => $user->id, 'owner_agent_id' => $agent->id])->success);
        $this->assertFalse($run('CreateRiskTool', ['management_book_id' => $book->id, 'title' => 'x', 'organization_entity_id' => 999999])->success);
        $this->assertFalse($run('CreateRiskTool', ['management_book_id' => $book->id])->success);
        $this->assertFalse($run('UpdateRiskTool', ['id' => $rid, 'owner_agent_id' => $agent->id])->success, 'owner_user bleibt gesetzt -> beide');

        // Update: Score neu, Status frei
        $r = $run('UpdateRiskTool', ['id' => $rid, 'impact' => 5, 'status' => 'treated', 'measures_implemented' => 'Zweitlieferant gelistet']);
        $this->assertTrue($r->success, json_encode($r));
        $this->assertSame(20, $r->data['risk']['score']);
        $this->assertSame('critical', $r->data['risk']['level']);
        $this->assertSame('treated', $r->data['risk']['status']);
        $this->assertSame('alternative Lieferanten prüfen', $r->data['risk']['measures_planned'], 'unverändert');
        $this->assertTrue($run('UpdateRiskTool', ['id' => $rid, 'due_date' => null])->success);
        $this->assertNull(\Platform\ManagementBook\Models\ManagementBookRisk::find($rid)->due_date);

        // Liste + Matrix
        $r = $run('ListRisksTool', ['management_book_id' => $book->id]);
        $this->assertTrue($r->success, json_encode($r));
        $this->assertSame(3, $r->data['total']);
        $this->assertSame(25, $r->data['risks'][0]['score'], 'nach Score absteigend');
        $this->assertNull($r->data['risks'][2]['score'], 'unbewertete am Ende');
        $this->assertSame(2, $r->data['overview']['by_level']['critical']);
        $this->assertSame(1, $r->data['overview']['unrated']);
        $this->assertCount(25, $r->data['overview']['matrix']);
        $cell = collect($r->data['overview']['matrix'])->first(fn ($c) => $c['probability'] === 5 && $c['impact'] === 5);
        $this->assertSame(1, $cell['count']);
        $this->assertSame(2, $run('ListRisksTool', ['management_book_id' => $book->id, 'level' => 'critical'])->data['total']);
        $this->assertSame(1, $run('ListRisksTool', ['status' => 'treated'])->data['total']);
        $this->assertSame(1, $run('ListRisksTool', ['search' => 'Zweitlieferant'])->data['total']);
        $this->assertFalse($run('ListRisksTool', ['level' => 'bogus'])->success);
        $this->assertSame('R-001', $run('ListRisksTool', ['id' => $rid])->data['risk']['number']);

        // Fremder User sieht nichts
        $otherCtx = new ToolContext($other, $team);
        $this->assertSame(0, (new \Platform\ManagementBook\Tools\ListRisksTool())->execute([], $otherCtx)->data['total'] ?? 0);

        // Risikobereitschaft am Book (über den Service: bookToArray nutzt MySQL-JSON-Funktionen, die sqlite nicht kennt)
        $service = app(\Platform\ManagementBook\Services\ManagementBookService::class);
        $service->update($book, ['risk_appetite' => 'moderate']);
        $this->assertSame('moderate', $book->fresh()->risk_appetite->value);
        try {
            $service->update($book, ['risk_appetite' => 'bogus']);
            $this->fail('ungültige risk_appetite muss abgelehnt werden');
        } catch (\Platform\ManagementBook\Exceptions\ManagementBookException $e) {
            $this->assertStringContainsString('risk_appetite', $e->getMessage());
        }
        $this->assertFalse($run('UpdateManagementBookTool', ['id' => $book->id, 'risk_appetite' => 'bogus'])->success);
        $this->assertSame('moderate', $run('ListRisksTool', ['management_book_id' => $book->id])->data['risk_appetite']);

        // Löschen (Soft-Delete)
        $this->assertTrue($run('DeleteRiskTool', ['id' => $critId])->success);
        $this->assertSame(2, $run('ListRisksTool', ['management_book_id' => $book->id])->data['total']);
        $this->assertNotNull(\Platform\ManagementBook\Models\ManagementBookRisk::withTrashed()->find($critId)->deleted_at);
    }
}
