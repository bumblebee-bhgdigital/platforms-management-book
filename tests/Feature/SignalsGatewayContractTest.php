<?php

namespace Tests\Feature;

require_once __DIR__ . '/UiTestHarness.php';

use Illuminate\Support\Facades\DB;
use Platform\ManagementBook\Events\AgendaProposalRejected;
use Platform\ManagementBook\Models\ManagementBookAgendaItem;
use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\ManagementBook\Models\ManagementBookDecision;
use Platform\ManagementBook\Models\ManagementBookMeeting;
use Platform\ManagementBook\Signals\ManagementBookCommitteeGateway;
use Tests\TestCase;

/**
 * Vertrag der Gremien-Brücke (Platform\Signals\Contracts\CommitteeGateway) aus Sicht des Books: liefert nur Book-Wissen
 * (Gremien, Sitzungen, Rahmen, Entwürfe, Rückweg) – mit beliebigem verknüpftem Objekt-Typ, ohne Signals zu kennen.
 * Aufruf: SIGNALS_MODULE_PATH=<signals> tests/run-in-taiste.sh tests/Feature/SignalsGatewayContractTest.php
 */
class SignalsGatewayContractTest extends TestCase
{
    use UiTestHarness;

    private ManagementBookCommitteeGateway $gw;
    private ManagementBookCommittee $la;
    private ManagementBookMeeting $next;
    private array $s;
    private int $node;

    protected function setUp(): void
    {
        parent::setUp();
        $contract = getenv('SIGNALS_MODULE_PATH') ?: dirname(__DIR__, 3) . '/signals';
        if (! is_file("$contract/src/Contracts/CommitteeGateway.php")) {
            $this->markTestSkipped('Vertrag CommitteeGateway nicht verfügbar (SIGNALS_MODULE_PATH).');
        }
        require_once "$contract/src/Contracts/CommitteeGateway.php";
        $this->bootModule();
        $this->withoutExceptionHandling();
        $this->s = $this->seedBook();
        $this->node = DB::table('organization_entities')->insertGetId(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'LA-Knoten', 'team_id' => $this->s['teamId'] ?? DB::table('teams')->value('id'),
            'user_id' => DB::table('users')->value('id'), 'entity_type_id' => DB::table('organization_entity_types')->value('id') ?? 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->la = ManagementBookCommittee::where('type', 'steering_committee')->first();
        $this->la->forceFill(['organization_entity_id' => $this->node, 'status' => 'active'])->save();
        $this->next = ManagementBookMeeting::where('committee_id', $this->la->id)->where('status', 'planned')->first();
        $this->gw = new ManagementBookCommitteeGateway();
    }

    public function test_lists_active_steering_committees_with_their_node(): void
    {
        $c = collect($this->gw->committees())->firstWhere('committee_id', $this->la->id);
        $this->assertSame($this->node, $c['entity_id']);
        $this->assertIsArray($c['responsible_user_ids']);
        $this->assertCount(1, $this->gw->committees(), 'nur Lenkungsausschüsse mit Knoten');
        $this->assertSame($this->next->id, $this->gw->nextMeeting($this->node)['meeting_id']);
    }

    public function test_draft_is_idempotent_and_outcomes_report_back_for_any_linked_type(): void
    {
        // beliebiger fremder Objekt-Typ – das Book weiß nicht, dass es ein Signal ist
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap(['any_object' => \Platform\Core\Models\User::class]);
        $obj = (int) DB::table('users')->value('id');
        $got = [];
        $this->gw->onOutcome(function (...$args) use (&$got) { $got[] = $args; });
        $q = ['title' => 'Priorität A/B', 'goal' => 'g', 'observation' => 'o', 'obstacle' => 'h', 'local_solution' => '4a', 'system_support' => '4b', 'unit_entity_id' => $this->node];
        $a = $this->gw->draftAgendaItem($this->next->id, $q, 'any_object', $obj);
        $this->assertSame($a['id'], $this->gw->draftAgendaItem($this->next->id, $q, 'any_object', $obj)['id']);
        $this->assertSame(ManagementBookAgendaItem::MORPH_ALIAS, $a['type']);
        $item = ManagementBookAgendaItem::find($a['id']);
        $this->assertTrue($item->isProposalDraft());

        $d = $this->next->decisions()->create(['team_id' => $this->next->team_id, 'agenda_item_id' => $item->id, 'text' => 'Rahmen für A', 'kind' => 'frame', 'created_by' => DB::table('users')->value('id')]);
        $this->assertSame(['any_object', $obj, 'frame', ManagementBookDecision::MORPH_ALIAS, $d->id], array_slice($got[0], 0, 5));
        $this->assertSame([ManagementBookDecision::MORPH_ALIAS, $d->id], [$this->gw->frames($this->node)[0]['type'], $this->gw->frames($this->node)[0]['decision_id']]);

        AgendaProposalRejected::dispatch($item, null);
        $this->assertSame(['any_object', $obj, 'return_to_unit'], array_slice($got[1], 0, 3));
    }

    /** Das Book kennt weder Signale noch Knotentypen der Organisation (auch nicht in der Brücke). */
    public function test_book_knows_neither_signals_nor_organization_type_codes(): void
    {
        $root = getenv('MB_MODULE_PATH');
        $src = implode("\n", array_map('file_get_contents', array_merge(glob("$root/src/*/*.php"), glob("$root/src/*/*/*.php"), glob("$root/resources/views/livewire/*/partials/*.php"))));
        $this->assertStringNotContainsString('signals_signal', $src);
        $this->assertStringNotContainsString('Platform\\Signals\\Models', $src);
        $bridge = file_get_contents("$root/src/Signals/ManagementBookCommitteeGateway.php");
        foreach (["'container'", "'board'", 'vsm_class', 'venture', 'Organization\\Models'] as $needle) {
            $this->assertStringNotContainsString($needle, $bridge, $needle);
        }
    }
}
