<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Platform\Core\Contracts\ToolContext;
use Tests\TestCase;

/**
 * Smoke-Test für #1052 (Sitzungs-Kinder). Läuft NUR in der Verifikations-App (/app, vendor/martin3r/*) mit
 * isolierter sqlite-:memory:-DB. Aufruf: Datei nach /app/tests/Feature kopieren, dann
 * DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/phpunit --filter MeetingChildrenSmokeTest
 */
class MeetingChildrenSmokeTest extends TestCase
{
    private const MODULE_FALLBACK = '/home/agent/agent-workspace/platforms-management-book';

    /** Modulpfad: MB_MODULE_PATH (tests/run-in-taiste.sh) oder der Pfad der Verifikations-App. */
    private static function module(): string
    {
        return getenv('MB_MODULE_PATH') ?: self::MODULE_FALLBACK;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // SICHERHEIT: nur isolierte sqlite-Memory-DB zulassen.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        spl_autoload_register(function ($c) {
            $p = 'Platform\\ManagementBook\\';
            if (str_starts_with($c, $p)) {
                $f = self::module() . '/src/' . str_replace('\\', '/', substr($c, strlen($p))) . '.php';
                if (is_file($f)) {
                    require $f;
                }
            }
        });
        $files = array_merge(
            glob(base_path('vendor/martin3r/platform-core/database/migrations/*.php')),
            glob(base_path('vendor/martin3r/platform-organization/database/migrations/*.php')),
            glob(self::module() . '/database/migrations/*.php'),
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
        $tables = ['management_book_agenda_items', 'management_book_documents', 'management_book_minutes', 'management_book_decisions', 'management_book_meeting_attendees'];
        foreach ($tables as $t) {
            $this->assertTrue(Schema::hasTable($t), $t);
        }
        $m = require self::module() . '/database/migrations/2026_11_20_000005_create_management_book_meeting_children_tables.php';
        $m->up(); // idempotent
        $m->down();
        foreach ($tables as $t) {
            $this->assertFalse(Schema::hasTable($t), "$t nach down");
        }
        $this->assertTrue(Schema::hasTable('management_book_meetings'), 'Eltern bleiben');
        $m->up();
        foreach ($tables as $t) {
            $this->assertTrue(Schema::hasTable($t), "$t nach erneutem up");
        }
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
        $committee = \Platform\ManagementBook\Models\ManagementBookCommittee::create(['team_id' => $teamId, 'management_book_id' => $book->id, 'name' => 'Vorstand']);
        $meeting = \Platform\ManagementBook\Models\ManagementBookMeeting::create(['team_id' => $teamId, 'committee_id' => $committee->id, 'title' => 'Sitzung 1', 'scheduled_at' => '2024-03-01 10:00']);

        // Models + Relationen
        $item = $meeting->agendaItems()->create(['title' => 'TOP 1', 'position' => 1]);
        $this->assertSame($teamId, $item->team_id, 'team_id aus Sitzung');
        $this->assertNotEmpty($item->uuid);
        $dec = $meeting->decisions()->create(['text' => 'Beschluss', 'agenda_item_id' => $item->id, 'due_date' => '2024-06-01', 'owner_agent_id' => $agent->id, 'linked_task_id' => 77]);
        $this->assertSame('open', $dec->fresh()->status->value);
        $min = $meeting->minutes()->create(['content' => '# P', 'approved_at' => '2024-03-05 09:00', 'approved_by' => $user->id]);
        $this->assertSame('2024-03-05', $min->fresh()->approved_at->toDateString(), 'historisch approved_at');
        $att = $meeting->attendees()->create(['user_id' => $user->id, 'role' => 'chair', 'attended' => true]);
        $doc = $meeting->documents()->create(['title' => 'Folien', 'type' => 'Präsentation']);
        $this->assertSame(1, $item->decisions()->count());
        $this->assertSame([1, 1, 1, 1, 1], [$meeting->agendaItems()->count(), $meeting->documents()->count(), $meeting->minutes()->count(), $meeting->decisions()->count(), $meeting->attendees()->count()]);
        $this->assertTrue($att->fresh()->attended);

        // Policies (erben von der Sitzung)
        $this->assertTrue(Gate::forUser($user)->allows('update', $item));
        $other = $U::create(['name' => 'O', 'email' => 'o@x.test', 'password' => 'x']);
        $this->assertFalse(Gate::forUser($other)->allows('view', $dec));
        $this->assertFalse(Gate::forUser($other)->allows('update', $att));

        // Tools
        $ctx = new ToolContext($user, $team);
        $ns = '\\Platform\\ManagementBook\\Tools\\';
        $run = function (string $cls, array $args) use ($ns, $ctx) {
            $c = $ns . $cls;

            return (new $c())->execute($args, $ctx);
        };

        $r = $run('CreateAgendaItemTool', ['meeting_id' => $meeting->id, 'title' => 'TOP 2', 'position' => 2, 'owner_user_id' => $user->id]);
        $this->assertTrue($r->success, json_encode($r));
        $topId = $r->data['agenda_item']['id'];
        $r = $run('ListAgendaItemsTool', ['meeting_id' => $meeting->id]);
        $this->assertSame(2, $r->data['total']);
        $this->assertSame('TOP 1', $r->data['agenda_items'][0]['title']);
        $this->assertFalse($run('CreateAgendaItemTool', ['meeting_id' => $meeting->id, 'title' => 'x', 'owner_user_id' => $user->id, 'owner_agent_id' => $agent->id])->success);
        $this->assertFalse($run('CreateAgendaItemTool', ['meeting_id' => $meeting->id])->success);
        $this->assertTrue($run('UpdateAgendaItemTool', ['id' => $topId, 'title' => 'TOP 2b', 'duration_minutes' => 15])->success);

        // Beschluss (historisch: Status direkt umgesetzt)
        $r = $run('CreateDecisionTool', ['meeting_id' => $meeting->id, 'text' => 'Alt', 'status' => 'implemented', 'due_date' => '2023-01-01', 'agenda_item_id' => $topId, 'linked_task_id' => 5]);
        $this->assertTrue($r->success, json_encode($r));
        $this->assertSame('implemented', $r->data['decision']['status']);
        $decId = $r->data['decision']['id'];
        $this->assertFalse($run('CreateDecisionTool', ['meeting_id' => $meeting->id, 'text' => 'x', 'status' => 'bogus'])->success);
        $r = $run('ListDecisionsTool', ['meeting_id' => $meeting->id, 'status' => 'open']);
        $this->assertSame(1, $r->data['total']);
        $this->assertTrue($run('UpdateDecisionTool', ['id' => $decId, 'status' => 'discarded', 'owner_user_id' => $user->id])->success);

        // Protokoll
        $r = $run('CreateMinutesTool', ['meeting_id' => $meeting->id, 'content' => 'Text', 'approved_at' => '2024-03-06 10:00', 'approved_by' => $user->id]);
        $this->assertTrue($r->success, json_encode($r));
        $minId = $r->data['minutes']['id'];
        $this->assertTrue($run('UpdateMinutesTool', ['id' => $minId, 'approved_at' => null, 'approved_by' => null])->success);
        $this->assertNull($min->newQuery()->find($minId)->approved_at);
        $this->assertSame(1, $run('ListMinutesTool', ['approved' => true])->data['total']);

        // Teilnehmer
        $r = $run('CreateMeetingAttendeeTool', ['meeting_id' => $meeting->id, 'agent_id' => $agent->id, 'role' => 'guest', 'attended' => true]);
        $this->assertTrue($r->success, json_encode($r));
        $attId = $r->data['attendee']['id'];
        $this->assertFalse($run('CreateMeetingAttendeeTool', ['meeting_id' => $meeting->id, 'agent_id' => $agent->id])->success, 'Duplikat');
        $this->assertFalse($run('CreateMeetingAttendeeTool', ['meeting_id' => $meeting->id])->success, 'weder user noch agent');
        $this->assertTrue($run('UpdateMeetingAttendeeTool', ['id' => $attId, 'attended' => false])->success);
        $this->assertSame(1, $run('ListMeetingAttendeesTool', ['attended' => true])->data['total']);
        $this->assertTrue($run('DeleteMeetingAttendeeTool', ['id' => $attId])->success);
        $this->assertNull(\Platform\ManagementBook\Models\ManagementBookMeetingAttendee::find($attId));

        // Unterlage + ContextFile-Verknüpfung
        $fileId = DB::table('context_files')->insertGetId(array_filter([
            'token' => 'tok1', 'team_id' => $teamId, 'user_id' => $user->id, 'disk' => 'local', 'path' => 'x/a.pdf',
            'file_name' => 'a.pdf', 'original_name' => 'a.pdf', 'mime_type' => 'application/pdf', 'file_size' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]) + (Schema::hasColumn('context_files', 'context_type') ? ['context_type' => 'x', 'context_id' => 1] : []));
        $r = $run('CreateDocumentTool', ['meeting_id' => $meeting->id, 'title' => 'Bericht', 'type' => 'Bericht', 'context_file_ids' => [$fileId], 'agenda_item_id' => $topId]);
        $this->assertTrue($r->success, json_encode($r));
        $docId = $r->data['document']['id'];
        $this->assertSame($fileId, $r->data['document']['files'][0]['context_file_id']);
        $this->assertFalse($run('CreateDocumentTool', ['meeting_id' => $meeting->id, 'title' => 'x', 'context_file_ids' => [99999]])->success);
        $r = $run('UpdateDocumentTool', ['id' => $docId, 'remove_context_file_ids' => [$fileId]]);
        $this->assertTrue($r->success, json_encode($r));
        $this->assertSame([], $r->data['document']['files']);
        $this->assertTrue($run('DeleteDocumentTool', ['id' => $docId])->success);
        $this->assertNotNull(\Platform\ManagementBook\Models\ManagementBookDocument::withTrashed()->find($docId)->deleted_at);

        // Soft-Delete Agenda -> Beschluss bleibt (Soft), Rechte bei gelöschter Sitzung
        $this->assertTrue($run('DeleteAgendaItemTool', ['id' => $topId])->success);
        $meeting->delete();
        $this->assertFalse($run('ListDecisionsTool', ['id' => $dec->id])->success, 'Kinder gelöschter Sitzung nicht lesbar');

        // fremdes Team
        $r = (new \Platform\ManagementBook\Tools\ListDecisionsTool())->execute(['team_id' => 9999], $ctx);
        $this->assertFalse($r->success);

        // Registrierung
        $reg = app(\Platform\Core\Tools\ToolRegistry::class);
        foreach (['agenda_items', 'documents', 'minutes', 'decisions', 'meeting_attendees'] as $res) {
            foreach (['GET', 'POST', 'PUT', 'DELETE'] as $v) {
                $this->assertNotNull($reg->get("management-book.$res.$v"), "$res.$v registriert");
            }
        }
    }
}
