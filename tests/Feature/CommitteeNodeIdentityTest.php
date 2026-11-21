<?php

namespace Tests\Feature;

require_once __DIR__ . '/UiTestHarness.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Platform\Core\Contracts\ToolContext;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\ManagementBook\Models\ManagementBookMeeting;
use Platform\ManagementBook\Services\CommitteeNodeService;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\EntityDimensionBridge;
use Tests\TestCase;

/**
 * Gremium = Organisations-Knoten: Verortung (Spalte + Bridge), Name/Beschreibung vom Knoten, Mitglieder/Vorsitz aus
 * Beziehungen (is_part_of/manages/is_active_in), Anlegen per Knoten oder create_node, Teilnehmer ohne Konto, Zeit.
 * Aufruf: tests/run-in-taiste.sh tests/Feature/CommitteeNodeIdentityTest.php
 */
class CommitteeNodeIdentityTest extends TestCase
{
    use UiTestHarness;

    private CommitteeNodeService $service;
    private array $s;
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootModule();
        $this->withoutExceptionHandling();
        $this->service = app(CommitteeNodeService::class);
        // Links auf Knoten (in der App registriert das Organisations-Modul die Route)
        if (! \Illuminate\Support\Facades\Route::has('organization.entities.show')) {
            \Illuminate\Support\Facades\Route::get('/organization/entities/{entity}', fn () => 'ok')->name('organization.entities.show');
            $this->app['router']->getRoutes()->refreshNameLookups();
        }
        if (! $this->service->available()) {
            $this->markTestSkipped('Organisations-Modul nicht verfügbar.');
        }
        $this->s = $this->seedBook();
        $this->seedOrganization();

        // Schreibrecht auf alle Knoten (der echte AuthzResolver braucht Grants/Closure)
        $this->app->instance(\Platform\Core\Authz\AuthzResolver::class, new class extends \Platform\Core\Authz\AuthzResolver {
            public function reachableEntityIds(\Platform\Core\Models\User $user, string $cap = 'read'): array
            {
                return OrganizationEntity::pluck('id')->map(fn ($i) => (int) $i)->all();
            }
        });
    }

    private function row(string $table, array $data): int
    {
        $data += ['uuid' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()];

        return DB::table($table)->insertGetId(array_intersect_key($data, array_flip(Schema::getColumnListing($table))));
    }

    /** Einheit BHG.DIGITAL → Governance → LA/Board; Personen (mit/ohne Konto), Agent; Beziehungs-Typen; Book an der Einheit. */
    private function seedOrganization(): void
    {
        $t = $this->s['teamId'];
        $u = $this->s['user']->id;
        if (! DB::table('organization_dimension_definitions')->where('key', 'entity')->exists()) {
            DB::table('organization_dimension_definitions')->insert(['key' => 'entity', 'name' => 'Entity', 'value_source' => 'entity', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        EntityDimensionBridge::flush();

        $group = $this->row('organization_entity_type_groups', ['code' => 'units', 'name' => 'Einheiten', 'team_id' => $t, 'is_active' => true, 'sort_order' => 1]);
        $type = fn ($code, $name) => DB::table('organization_entity_types')->where('code', $code)->value('id')
            ?? $this->row('organization_entity_types', ['code' => $code, 'name' => $name, 'team_id' => $t, 'is_active' => true, 'sort_order' => 1, 'entity_type_group_id' => $group]);
        $this->ids['t_unit'] = $type('business_unit', 'Geschäftseinheit');
        $this->ids['t_container'] = $type('container', 'Container');
        $this->ids['t_board'] = $type('board', 'Board / Gremium');
        $this->ids['t_person'] = $type('person', 'Person');
        $this->ids['t_agent'] = $type('agent', 'Agent');

        $e = fn ($name, $typeId, $parent = null, array $extra = []) => $this->row('organization_entities', $extra + [
            'name' => $name, 'team_id' => $t, 'user_id' => $u, 'entity_type_id' => $typeId, 'parent_entity_id' => $parent, 'is_active' => true,
        ]);
        $this->ids['unit'] = $e('BHG.DIGITAL', $this->ids['t_unit']);
        $this->ids['gov'] = $e('Governance', $this->ids['t_container'], $this->ids['unit']);
        $this->ids['la'] = $e('BHG.DIGITAL Lenkungsausschuss', $this->ids['t_board'], $this->ids['gov'], ['description' => 'Regelschleife zwischen den Einheiten']);
        $this->ids['board'] = $e('BHG.DIGITAL Board', $this->ids['t_board'], $this->ids['gov']);
        $this->ids['team_node'] = $e('Team Entwicklung', $this->ids['t_unit'], $this->ids['unit']);
        $this->ids['martin'] = $e('Martin Erren', $this->ids['t_person'], null, ['linked_user_id' => $this->s['user']->id]);
        $this->ids['burkhard'] = $e('Burkhard Schmitz', $this->ids['t_person']);
        $this->ids['ratchet'] = $e('Ratchet', $this->ids['t_agent'], null, ['linked_user_id' => $this->s['agent']->id]);

        $rel = fn ($code, $name) => DB::table('organization_entity_relation_types')->where('code', $code)->value('id')
            ?? $this->row('organization_entity_relation_types', ['code' => $code, 'name' => $name, 'is_active' => true, 'sort_order' => 1]);
        $this->ids['r_member'] = $rel('is_part_of', 'Ist Teil von');
        $this->ids['r_chair'] = $rel('manages', 'Führt an');
        $this->ids['r_agent'] = $rel('is_active_in', 'Ist aktiv in');

        // Bestand wie auf office: Burkhard „führt an“ den LA, Martin ist Teil davon
        $this->relate($this->ids['burkhard'], $this->ids['la'], $this->ids['r_chair']);
        $this->relate($this->ids['burkhard'], $this->ids['la'], $this->ids['r_member']);
        $this->relate($this->ids['martin'], $this->ids['la'], $this->ids['r_member']);
        $this->relate($this->ids['ratchet'], $this->ids['la'], $this->ids['r_agent']);

        $this->s['agent']->forceFill(['type' => 'ai_user'])->save();
        EntityDimensionBridge::createLink($this->ids['unit'], ManagementBook::MORPH_ALIAS, $this->s['book']->id, ['team_id' => $t]);
        EntityDimensionBridge::flush();
    }

    private function relate(int $from, int $to, int $typeId): void
    {
        $this->row('organization_entity_relationships', ['from_entity_id' => $from, 'to_entity_id' => $to, 'relation_type_id' => $typeId, 'team_id' => $this->s['teamId'], 'user_id' => $this->s['user']->id]);
    }

    private function ctx(): ToolContext
    {
        return new ToolContext($this->s['user'], $this->s['team']);
    }

    public function test_placement_column_bridge_uniqueness_and_identity_from_node(): void
    {
        $la = $this->s['committee'];
        $this->service->place($la, $this->ids['la']);
        $la->refresh();

        $this->assertSame($this->ids['la'], $la->organization_entity_id);
        $this->assertSame([$this->ids['la']], EntityDimensionBridge::linksForLinkables([ManagementBookCommittee::MORPH_ALIAS], [$la->id], false)->pluck('entity_id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame('BHG.DIGITAL Lenkungsausschuss', $la->name, 'Name kommt vom Knoten');
        $this->assertSame('Regelschleife zwischen den Einheiten', $la->description);

        // Ein Knoten trägt höchstens ein Gremium
        $strategy = ManagementBookCommittee::where('type', 'strategy_meeting')->firstOrFail();
        try {
            $this->service->place($strategy, $this->ids['la']);
            $this->fail('Doppelte Verortung erlaubt');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('nur ein Gremium', $e->getMessage());
        }

        // Externe Verknüpfung (organization.entity.link.POST) zieht die Spalte nach
        EntityDimensionBridge::createLink($this->ids['board'], ManagementBookCommittee::MORPH_ALIAS, $strategy->id, ['team_id' => $this->s['teamId']]);
        $this->assertSame($this->ids['board'], $strategy->fresh()->organization_entity_id);

        $this->service->unplace($strategy);
        $this->assertNull($strategy->fresh()->organization_entity_id);
        $this->assertSame('Strategiemeeting GF', $strategy->fresh()->name, 'ohne Knoten: Fallback auf Book-Spalte');
    }

    public function test_members_and_chair_from_relationships_and_writes(): void
    {
        $la = $this->s['committee'];
        $this->service->place($la, $this->ids['la']);
        $la->refresh();

        $members = $this->service->members($la)->keyBy('name');
        $this->assertCount(3, $members);
        $this->assertSame('chair', $members['Burkhard Schmitz']['role']);
        $this->assertNull($members['Burkhard Schmitz']['user_id'], 'Person ohne Konto');
        $this->assertSame('member', $members['Martin Erren']['role']);
        $this->assertSame($this->s['user']->id, $members['Martin Erren']['user_id'], 'User über linked_user_id');
        $this->assertSame('agent', $members['Ratchet']['role']);
        $this->assertSame($this->s['agent']->id, $members['Ratchet']['agent_id']);
        $this->assertSame('organization', $members['Ratchet']['source']);

        // Vorsitz wechseln: Martin führt an, Burkhards „führt an“ wird gelöst (er bleibt Mitglied)
        $this->service->setMember($la, $this->ids['martin'], 'chair');
        $members = $this->service->members($la)->keyBy('name');
        $this->assertSame('chair', $members['Martin Erren']['role']);
        $this->assertSame('member', $members['Burkhard Schmitz']['role']);
        $this->assertSame('Martin Erren', $this->service->chair($la)['name']);

        // Tool: members[] ersetzt die Liste am Knoten (user_id → Personen-Knoten, entity_id für Personen ohne Konto)
        $r = (new \Platform\ManagementBook\Tools\UpdateCommitteeTool())->execute(['id' => $la->id, 'members' => [
            ['user_id' => $this->s['user']->id, 'role' => 'chair'],
            ['entity_id' => $this->ids['burkhard'], 'role' => 'member'],
        ]], $this->ctx());
        $this->assertTrue($r->success, json_encode($r));
        $this->assertSame('organization', $r->data['committee']['members_source']);
        $this->assertEqualsCanonicalizing(['Martin Erren', 'Burkhard Schmitz'], array_column($r->data['committee']['members'], 'name'));
        $this->assertSame(0, DB::table('organization_entity_relationships')->where('from_entity_id', $this->ids['ratchet'])->where('to_entity_id', $this->ids['la'])->whereNull('deleted_at')->count(), 'Ratchet entfernt');
        $this->assertSame('Martin Erren', $r->data['committee']['chair']['name']);

        // Name/Beschreibung über das Tool landen am Knoten
        $r = (new \Platform\ManagementBook\Tools\UpdateCommitteeTool())->execute(['id' => $la->id, 'name' => 'BHG.DIGITAL LA', 'description' => 'Neu'], $this->ctx());
        $this->assertTrue($r->success, json_encode($r));
        $this->assertSame('BHG.DIGITAL LA', OrganizationEntity::find($this->ids['la'])->name);
        $this->assertSame('Neu', OrganizationEntity::find($this->ids['la'])->description);
        $this->assertSame('BHG.DIGITAL LA', $r->data['committee']['name']);
    }

    public function test_create_tool_with_existing_node_and_create_node(): void
    {
        $book = $this->s['book'];
        $tool = new \Platform\ManagementBook\Tools\CreateCommitteeTool();

        // ohne Knoten → Fehler (Gremium ist ein Knoten)
        $r = $tool->execute(['management_book_id' => $book->id, 'name' => 'Board'], $this->ctx());
        $this->assertFalse($r->success);

        // kein Gremien-Knoten → Fehler
        $r = $tool->execute(['management_book_id' => $book->id, 'organization_entity_id' => $this->ids['team_node']], $this->ctx());
        $this->assertFalse($r->success);
        $this->assertStringContainsString('kein Gremien-Knoten', json_encode($r->error ?? $r, JSON_UNESCAPED_UNICODE));

        // bestehender Board-Knoten
        $r = $tool->execute(['management_book_id' => $book->id, 'organization_entity_id' => $this->ids['board'], 'type' => 'board', 'cadence' => 'quartalsweise'], $this->ctx());
        $this->assertTrue($r->success, json_encode($r));
        $this->assertSame('BHG.DIGITAL Board', $r->data['committee']['name']);
        $this->assertSame($this->ids['board'], $r->data['committee']['organization_entity_id']);

        // derselbe Knoten ein zweites Mal → Fehler
        $r = $tool->execute(['management_book_id' => $book->id, 'organization_entity_id' => $this->ids['board']], $this->ctx());
        $this->assertFalse($r->success);

        // create_node → neuer Gremien-Knoten unter „Governance“ der Einheit des Books
        $r = $tool->execute(['management_book_id' => $book->id, 'type' => 'strategy_meeting', 'create_node' => ['name' => 'BHG.DIGITAL Strategiemeeting', 'description' => 'Jährlich']], $this->ctx());
        $this->assertTrue($r->success, json_encode($r));
        $node = OrganizationEntity::find($r->data['committee']['organization_entity_id']);
        $this->assertSame('BHG.DIGITAL Strategiemeeting', $node->name);
        $this->assertSame($this->ids['gov'], (int) $node->parent_entity_id);
        $this->assertSame($this->ids['t_board'], (int) $node->entity_type_id);

        // Vorschläge: LA-Knoten ist noch frei, Board/Strategie sind vergeben
        $sugg = $this->service->suggestions($book)->pluck('id')->map(fn ($i) => (int) $i)->all();
        $this->assertSame([$this->ids['la']], $sugg);
        $this->get(route('management-book.books.show', $book) . '?tab=committees')->assertOk();
    }

    public function test_attendees_from_node_members_and_time_rolls_up(): void
    {
        $la = $this->s['committee'];
        $this->service->place($la, $this->ids['la']);
        $meeting = $this->s['next'];

        $component = new \Platform\ManagementBook\Livewire\Meeting\Show();
        $component->meeting = $meeting;
        $component->adoptMembers();
        $att = $meeting->attendees()->get();
        $this->assertNotNull($att->firstWhere('organization_entity_id', $this->ids['burkhard']), 'Person ohne Konto als Teilnehmer');
        $this->assertSame('Burkhard Schmitz', $att->firstWhere('organization_entity_id', $this->ids['burkhard'])->displayName());
        $this->assertNotNull($att->firstWhere('user_id', $this->s['user']->id));
        $this->assertNotNull($att->firstWhere('agent_id', $this->s['agent']->id));

        // Tool: Teilnehmer als Personen-Knoten
        $r = (new \Platform\ManagementBook\Tools\CreateMeetingAttendeeTool())->execute(['meeting_id' => $this->s['past']->id, 'organization_entity_id' => $this->ids['burkhard']], $this->ctx());
        $this->assertTrue($r->success, json_encode($r));
        $this->assertSame('Burkhard Schmitz', $r->data['meeting_attendee']['name'] ?? $r->data['attendee']['name'] ?? null);

        // Zeit auf die Sitzung buchen → rollt zum Gremien-Knoten hoch
        $component->timeForm = ['minutes' => 90, 'work_date' => now()->toDateString(), 'note' => 'Vorbereitung'];
        $component->bookTime();
        $this->assertSame(90, (int) \Platform\Organization\Models\OrganizationTimeEntry::where('context_type', ManagementBookMeeting::class)->where('context_id', $meeting->id)->sum('minutes'));

        $resolver = app(\Platform\Organization\Services\EntityTimeResolver::class);
        $minutes = (int) $resolver->buildTimeEntryQuery(OrganizationEntity::find($this->ids['la']))->sum('minutes');
        $this->assertSame(90, $minutes, 'Sitzungszeit zählt am Gremien-Knoten');
        $this->assertSame(ManagementBookCommittee::class, \Platform\Organization\Services\ContextTypeRegistry::resolve(ManagementBookCommittee::MORPH_ALIAS), 'Gremium als Zeit-Kontext buchbar');

        // Seiten rendern
        $this->get(route('management-book.committees.show', $la))->assertOk()->assertSee('Burkhard Schmitz')->assertSee('In der Organisation bearbeiten', false);
        $this->get(route('management-book.meetings.show', $meeting))->assertOk()->assertSee('Zeit buchen')->assertSee('Burkhard Schmitz');
    }
}
