<?php

namespace Tests\Feature;

require_once __DIR__ . '/UiTestHarness.php';

use Platform\Core\Contracts\ToolContext;
use Platform\ManagementBook\Enums\CommitteeType;
use Platform\ManagementBook\Formats\BoardFormat;
use Platform\ManagementBook\Formats\CommitteeFormat;
use Platform\ManagementBook\Formats\GenericFormat;
use Platform\ManagementBook\Formats\SteeringCommitteeFormat;
use Platform\ManagementBook\Formats\StrategyMeetingFormat;
use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\ManagementBook\Models\ManagementBookMeeting;
use Platform\ManagementBook\Organization\ManagementBookEntityLinkProvider;
use Tests\TestCase;

/**
 * Formate je Gremientyp (LA, Strategiemeeting, Board) und Gremien an Organisations-Knoten.
 * Aufruf: tests/run-in-taiste.sh tests/Feature/CommitteeFormatsTest.php
 */
class CommitteeFormatsTest extends TestCase
{
    use UiTestHarness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootModule();
        $this->withoutExceptionHandling();
    }

    /** Strategie-Gremium aus dem Seed mit einer gehaltenen Sitzung (17.11.-artig). */
    private function seedStrategyMeeting(array $s): array
    {
        $strategy = ManagementBookCommittee::where('management_book_id', $s['book']->id)->where('type', 'strategy_meeting')->firstOrFail();
        $meeting = ManagementBookMeeting::create([
            'team_id' => $s['teamId'], 'committee_id' => $strategy->id, 'title' => 'Strategiemeeting 2026',
            'scheduled_at' => now()->subDays(4), 'status' => 'held',
        ]);
        $item = $meeting->agendaItems()->create([
            'title' => 'Wirkungsgrad beim Kunden', 'position' => 1, 'kind' => 'assessment',
            'observation' => 'Die Organisation steht, beim Kunden kommt zu wenig an', 'goal' => 'Kundenbeziehung als Fokusraum?',
            'outcome_checks' => ['direction_clear'],
        ]);
        $meeting->agendaItems()->create(['title' => 'Agenten im Einsatz', 'position' => 2, 'kind' => 'info', 'description' => 'Bericht']);
        $meeting->decisions()->create(['text' => 'Fokusraum Clients for Life', 'kind' => 'focus_area', 'agenda_item_id' => $item->id, 'status' => 'implemented']);

        return [$strategy, $meeting];
    }

    public function test_format_resolution_and_assessment(): void
    {
        $this->assertInstanceOf(SteeringCommitteeFormat::class, CommitteeType::STEERING_COMMITTEE->format());
        $this->assertInstanceOf(StrategyMeetingFormat::class, CommitteeType::STRATEGY_MEETING->format());
        $this->assertInstanceOf(BoardFormat::class, CommitteeType::BOARD->format());
        $this->assertInstanceOf(GenericFormat::class, CommitteeType::OTHER->format());
        $this->assertInstanceOf(GenericFormat::class, CommitteeFormat::for(null));

        $la = new SteeringCommitteeFormat();
        $this->assertTrue($la->measuresCapability());
        $this->assertSame(['goal', 'observation', 'obstacle', 'local_solution', 'system_support'], array_keys($la->agendaFields()));
        $this->assertCount(6, $la->outcomeChecks());
        $this->assertSame('Handlungsfähiger als vorher', $la->verdict(5, 6)['label']);
        $this->assertSame('Teilweise handlungsfähiger', $la->verdict(3, 6)['label']);
        $this->assertSame('Eher berichtet als reguliert', $la->verdict(1, 6)['label']);

        $st = new StrategyMeetingFormat();
        $this->assertFalse($st->measuresCapability());
        $this->assertSame(['observation', 'goal'], array_keys($st->agendaFields()));
        $this->assertNull($st->validateDecisionKind('focus_area'));
        $this->assertStringContainsString('direction, objective, guardrail, focus_area', (string) $st->validateDecisionKind('measure'));
        $this->assertNotNull($st->validateAgendaKind('regulation'));
        $this->assertNotNull($st->validateChecks(['blockers_removed']));

        // info-Punkte zählen nicht; LA-Kriterien an einem Strategiepunkt zählen nicht
        $items = [
            (object) ['kind' => 'direction', 'goal' => 'x', 'outcome_checks' => ['direction_clear', 'blockers_removed']],
            (object) ['kind' => 'info', 'goal' => 'y', 'outcome_checks' => ['direction_clear']],
            (object) ['kind' => null, 'obstacle' => 'nur LA-Feld', 'outcome_checks' => []],
        ];
        $a = $st->assess($items);
        $this->assertSame(['done' => 1, 'max' => 5, 'guided' => 1, 'pct' => 20], $a);

        $this->assertArrayHasKey('agenda_kinds', $st->toArray());
        $this->assertSame('Fokusraum', CommitteeFormat::anyDecisionKindLabel('focus_area'));
    }

    public function test_tools_validate_kind_and_checks_per_format(): void
    {
        $s = $this->seedBook();
        [$strategy, $sMeeting] = $this->seedStrategyMeeting($s);
        $ctx = new ToolContext($s['user'], $s['team']);
        $run = fn (string $cls, array $args) => (new ('\\Platform\\ManagementBook\\Tools\\' . $cls)())->execute($args, $ctx);

        // LA: Strategie-Art ist falsch, LA-Art richtig
        $r = $run('CreateAgendaItemTool', ['meeting_id' => $s['next']->id, 'title' => 'X', 'kind' => 'direction']);
        $this->assertFalse($r->success);
        $this->assertStringContainsString('regulation, info', json_encode($r, JSON_UNESCAPED_UNICODE));
        $r = $run('CreateAgendaItemTool', ['meeting_id' => $s['next']->id, 'title' => 'Ressourcenkonflikt', 'kind' => 'regulation', 'outcome_checks' => ['blockers_removed']]);
        $this->assertTrue($r->success, json_encode($r));
        $this->assertSame('Regulation', $r->data['agenda_item']['kind_label']);

        // Strategie: direction ok, LA-Kriterium abgelehnt, Strategiekriterium ok
        $r = $run('CreateAgendaItemTool', ['meeting_id' => $sMeeting->id, 'title' => 'Szenarien', 'kind' => 'direction', 'outcome_checks' => ['blockers_removed']]);
        $this->assertFalse($r->success);
        $r = $run('CreateAgendaItemTool', ['meeting_id' => $sMeeting->id, 'title' => 'Szenarien', 'kind' => 'direction', 'outcome_checks' => ['priorities_set']]);
        $this->assertTrue($r->success, json_encode($r));
        $this->assertSame('direction', $r->data['agenda_item']['kind']);

        // Ohne kind: Standard-Art des Formats in der Ausgabe
        $r = $run('CreateAgendaItemTool', ['meeting_id' => $sMeeting->id, 'title' => 'Lage']);
        $this->assertSame('assessment', $r->data['agenda_item']['kind']);

        // Beschlüsse
        $this->assertFalse($run('CreateDecisionTool', ['meeting_id' => $sMeeting->id, 'text' => 'Maßnahme', 'kind' => 'measure'])->success);
        $r = $run('CreateDecisionTool', ['meeting_id' => $sMeeting->id, 'text' => 'Szenarioplanung', 'kind' => 'direction']);
        $this->assertTrue($r->success, json_encode($r));
        $this->assertSame('Richtungsentscheidung', $r->data['decision']['kind_label']);
        $did = $r->data['decision']['id'];
        $this->assertFalse($run('UpdateDecisionTool', ['id' => $did, 'kind' => 'approval'])->success);
        $this->assertTrue($run('UpdateDecisionTool', ['id' => $did, 'kind' => 'guardrail'])->success);
        $r = $run('CreateDecisionTool', ['meeting_id' => $s['next']->id, 'text' => 'Zurück an den Bereich']);
        $this->assertSame('measure', $r->data['decision']['kind']);

        // Gremium-Tool erklärt das Format
        $r = $run('ListCommitteesTool', ['id' => $strategy->id]);
        $this->assertTrue($r->success);
        $this->assertSame('strategy_meeting', $r->data['committee']['format']['key']);
        $this->assertArrayHasKey('focus_area', $r->data['committee']['format']['decision_kinds']);
        $r = $run('ListCommitteesTool', ['management_book_id' => $s['book']->id]);
        $this->assertSame('Regulation', collect($r->data['committees'])->firstWhere('type', 'steering_committee')['format']['name']);
    }

    public function test_strategy_meeting_page_has_no_la_texts(): void
    {
        $s = $this->seedBook();
        [$strategy, $sMeeting] = $this->seedStrategyMeeting($s);

        $this->get(route('management-book.meetings.show', $sMeeting))->assertOk()
            ->assertSee('Format Richtung')->assertSee('Richtungsklarheit')->assertSee('Ausgangslage – wo stehen wir?')
            ->assertSee('Richtungsfrage – was müssen wir entscheiden?')->assertSee('Haltung im Strategiemeeting')
            ->assertSee('Richtungsentscheidungen ins Management Book übertragen')->assertSee('Fokusraum')
            ->assertSee('Standortbestimmung')->assertSee('Information')->assertSee('Richtung geben, nicht regulieren')
            ->assertDontSee('Haltung im LA')->assertDontSee('vier Fragen')->assertDontSee('Handlungsfähigkeit')
            ->assertDontSee('Was hindert euch momentan?')->assertDontSee('Ohne Antwort auf 4a');

        // LA-Seite unverändert
        $this->get(route('management-book.meetings.show', $s['past']))->assertOk()
            ->assertSee('Haltung im LA')->assertSee('Handlungsfähigkeit')->assertSee('Was wollten wir erreichen?')
            ->assertSee('Ohne Antwort auf 4a droht Mikromanagement');

        // Gremium-Seite zeigt Format und Verortung
        $this->get(route('management-book.committees.show', $strategy))->assertOk()
            ->assertSee('Format: Richtung')->assertSee('Richtungsklarheit')->assertSee('Verortung')
            ->assertDontSee('Ist die Organisation danach handlungsfähiger');
        $this->get(route('management-book.books.show', $s['book']) . '?tab=committees')->assertOk()
            ->assertSee('Format Regulation')->assertSee('Format Richtung');
    }

    public function test_meeting_component_uses_format(): void
    {
        $s = $this->seedBook();
        [, $sMeeting] = $this->seedStrategyMeeting($s);
        $c = app(\Platform\ManagementBook\Livewire\Meeting\Show::class);
        $c->setId('t' . uniqid());
        $c->setName('test');
        $c->mount($sMeeting);

        $c->openItem();
        $this->assertSame('assessment', $c->itemForm['kind']);
        $this->assertArrayNotHasKey('local_solution', $c->itemForm, 'LA-Felder gehören nicht zum Strategieformat');
        $c->itemForm['title'] = 'Planzahlen 2027';
        $c->itemForm['kind'] = 'direction';
        $c->itemForm['goal'] = '800 T€ freigeben?';
        $c->saveItem();
        $item = $sMeeting->agendaItems()->where('title', 'Planzahlen 2027')->firstOrFail();
        $this->assertSame('direction', $item->kind);

        $c->toggleCheck($item->id, 'blockers_removed'); // LA-Kriterium: ignoriert
        $c->toggleCheck($item->id, 'priorities_set');
        $this->assertSame(['priorities_set'], $item->fresh()->outcome_checks);

        $c->openDecision();
        $this->assertSame('direction', $c->decisionForm['kind']);
    }

    public function test_capability_metric_counts_only_steering_meetings(): void
    {
        $s = $this->seedBook();
        // jüngere gehaltene Strategiesitzung mit geführtem Punkt (1 von 5) darf die LA-Kennzahl nicht verändern
        $this->seedStrategyMeeting($s);

        $m = (new ManagementBookEntityLinkProvider())->metrics('management_book', [10 => [$s['book']->id]])[10];
        $this->assertSame(83, $m['mb_meeting_capability_percent']);
        $this->assertSame(2, $m['mb_meetings_held_30d']);
    }

    public function test_committee_link_provider(): void
    {
        $s = $this->seedBook();
        [$strategy] = $this->seedStrategyMeeting($s);
        $provider = new ManagementBookEntityLinkProvider();

        $this->assertContains(ManagementBookCommittee::MORPH_ALIAS, $provider->morphAliases());
        $this->assertSame('management-book.committees.show', $provider->linkTypeConfig()[ManagementBookCommittee::MORPH_ALIAS]['route']);
        $meta = $provider->extractMetadata(ManagementBookCommittee::MORPH_ALIAS, $s['committee']->load('book', 'chair'));
        $this->assertSame('Lenkungsausschuss', $meta['type']);
        $this->assertSame('Regulation', $meta['format']);

        $result = $provider->metrics(ManagementBookCommittee::MORPH_ALIAS, [
            39 => [$s['committee']->id],
            40 => [$strategy->id],
            41 => [999999],
        ]);
        $la = $result[39];
        $this->assertSame(1, $la['mbc_committees']);
        $this->assertSame(1, $la['mbc_meetings_held_30d']);
        $this->assertSame(1, $la['mbc_decisions_open']);
        $this->assertSame(1, $la['mbc_decisions_overdue']);
        $this->assertSame(7, $la['mbc_days_to_next_meeting']);
        $this->assertSame(83, $la['mbc_meeting_capability_percent']);
        $this->assertArrayNotHasKey('mbc_meeting_capability_percent', $result[40], 'Strategiemeeting misst keine Handlungsfähigkeit');
        $this->assertSame(1, $result[40]['mbc_meetings_held_30d']);
        $this->assertArrayNotHasKey(41, $result);

        foreach (array_keys($la) as $key) {
            $this->assertArrayHasKey($key, $provider->metricDefinitions());
        }

        // Organisation: Typ ist adressierbar und die Definitionen passieren die Registry
        if (class_exists(\Platform\Organization\Services\ObjectTypeRegistry::class)) {
            $this->assertSame(ManagementBookCommittee::MORPH_ALIAS, \Platform\Organization\Services\ObjectTypeRegistry::resolve('management_book_committee'));
        }
        if (class_exists(\Platform\Organization\Services\EntityLinkRegistry::class)) {
            $registry = resolve(\Platform\Organization\Services\EntityLinkRegistry::class);
            $registry->register($provider);
            $all = $registry->allMetricDefinitions(); // wirft bei ungültigem subset_of/primary
            $this->assertSame('Gremien', $all['mbc_committees']['label']);
            $batch = $registry->computeMetricsBatch([39 => [ManagementBookCommittee::MORPH_ALIAS => [$s['committee']->id]]]);
            $this->assertSame(83, $batch[39]['mbc_meeting_capability_percent']);
        }
    }

    public function test_committee_placement_via_tool_and_service(): void
    {
        $s = $this->seedBook();
        $service = app(\Platform\ManagementBook\Services\CommitteeNodeService::class);
        if (! $service->available() || ! class_exists(\Platform\Organization\Models\OrganizationEntity::class)) {
            $this->markTestSkipped('Organisations-Modul nicht verfügbar.');
        }

        try {
            if (! \Illuminate\Support\Facades\DB::table('organization_dimension_definitions')->where('key', 'entity')->exists()) {
                \Illuminate\Support\Facades\DB::table('organization_dimension_definitions')->insert(['key' => 'entity', 'name' => 'Entity', 'value_source' => 'entity', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            \Platform\Organization\Services\EntityDimensionBridge::flush();
            $row = function (string $table, array $data): int {
                $data += ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'created_at' => now(), 'updated_at' => now()];

                return \Illuminate\Support\Facades\DB::table($table)->insertGetId(array_intersect_key($data, array_flip(\Illuminate\Support\Facades\Schema::getColumnListing($table))));
            };
            $groupId = $row('organization_entity_type_groups', ['code' => 'governance', 'name' => 'Gremien', 'team_id' => $s['teamId'], 'is_active' => true, 'sort_order' => 1]);
            $typeId = $row('organization_entity_types', ['code' => 'committee', 'name' => 'Gremium', 'team_id' => $s['teamId'], 'is_active' => true, 'sort_order' => 1, 'entity_type_group_id' => $groupId]);
            $entityId = $row('organization_entities', ['name' => 'BHG.DIGITAL Lenkungsausschuss', 'team_id' => $s['teamId'], 'user_id' => $s['user']->id, 'entity_type_id' => $typeId, 'is_active' => true]);
            $entity = \Platform\Organization\Models\OrganizationEntity::findOrFail($entityId);
            $service->place($s['committee'], $entity->id);
        } catch (\Throwable $e) {
            $this->fail('Bridge: ' . $e->getMessage());
        }
        if ($service->nodeIds($s['committee']) === []) {
            $this->fail('Bridge ohne Link');
        }

        $this->assertSame([$entity->id], $service->nodeIds($s['committee']));
        $this->get(route('management-book.committees.show', $s['committee']))->assertOk()->assertSee('BHG.DIGITAL Lenkungsausschuss');

        $ctx = new ToolContext($s['user'], $s['team']);
        $r = (new \Platform\ManagementBook\Tools\ListCommitteesTool())->execute(['id' => $s['committee']->id], $ctx);
        $this->assertSame($entity->id, $r->data['committee']['organization_nodes'][0]['entity_id']);

        $r = (new \Platform\ManagementBook\Tools\UpdateCommitteeTool())->execute(['id' => $s['committee']->id, 'organization_entity_id' => 0], $ctx);
        $this->assertTrue($r->success, json_encode($r));
        $this->assertSame([], $service->nodeIds($s['committee']));
    }
}
