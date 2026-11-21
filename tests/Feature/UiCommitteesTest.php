<?php

namespace Tests\Feature;

require_once __DIR__ . '/UiTestHarness.php';

use Platform\ManagementBook\Livewire\Book\Committees;
use Platform\ManagementBook\Livewire\Committee\Show as CommitteeShow;
use Platform\ManagementBook\Livewire\Meeting\Show as MeetingShow;
use Tests\TestCase;

/**
 * Gremien-Tab, Gremium-Seite und Sitzungsseite (LA-Leitfaden): Render per HTTP, Aktionen direkt an der Komponente + DB.
 * Aufruf: tests/run-in-taiste.sh tests/Feature/UiCommitteesTest.php
 */
class UiCommitteesTest extends TestCase
{
    use UiTestHarness;

    /** Komponente direkt instanziieren (Livewire::test ist in der Verifikations-App ohne Snapshot). */
    private function makeComponent(string $class, array $mount)
    {
        $c = app($class);
        $c->setId('t' . uniqid());
        $c->setName('test');
        $c->mount(...$mount);

        return $c;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootModule();
        $this->withoutExceptionHandling();
    }

    public function test_pages_render(): void
    {
        $d = $this->seedBook();
        $this->get(route('management-book.books.show', $d['book']) . '?tab=committees')->assertOk()
            ->assertSee('Lenkungsausschuss')->assertSee('Strategiemeeting GF')->assertSee('1 offen · 1 überfällig')
            ->assertSee('Richtung geben, Zusammenspiel regulieren')->assertSee('Nächste Sitzung');

        $this->get(route('management-book.committees.show', $d['committee']))->assertOk()
            ->assertSee('Lokal vor übergreifend')->assertSee('LA Dezember')->assertSee('LA Oktober')
            ->assertSee('Zusatzschicht im Dezember')->assertSee('handlungsfähig')->assertSee('Vorsitz')
            ->assertSee('Gehört das hier hin?')->assertSee('Plane den Rhythmus')
            ->assertSee('Ein Beschluss ist erst wirksam')->assertSee('Vertiefen in der Academy');

        $this->get(route('management-book.meetings.show', $d['past']))->assertOk()
            ->assertSee('Kapazität Dezember')->assertSee('Was wollten wir erreichen?')->assertSee('Drei Großaufträge liefern')
            ->assertSee('Handlungsfähiger als vorher')->assertSee('Zusatzschicht im Dezember')->assertSee('Freigegeben')
            ->assertSee('Kapazitätsplanung')->assertSee('Gremiumsmitglieder übernehmen')
            ->assertSee('Ohne Antwort auf 4a droht Mikromanagement') // Punkt 2 ohne Leitfaden
            ->assertSee('Jetzt: Nachbereiten')->assertSee('Haltung im LA')->assertSee('Vom Statusbericht zur Regulation');

        $this->get(route('management-book.meetings.show', $d['next']))->assertOk()->assertSee('Personalrisiko')
            ->assertSee('Jetzt: Vorbereiten');
    }

    public function test_empty_committees_offer_presets(): void
    {
        $d = $this->seedBook();
        $book2 = app(\Platform\ManagementBook\Services\ManagementBookService::class)->create($d['user'], $d['team'], ['title' => 'Leeres Book']);
        $this->get(route('management-book.books.show', $book2) . '?tab=committees')->assertOk()
            ->assertSee('Noch keine Gremien')->assertSee('Strategiemeeting GF anlegen')->assertSee('Lenkungsausschuss anlegen');

        $c = $this->makeComponent(Committees::class, [$book2->id]);
        $c->openCreate('steering_committee');
        $this->assertTrue($c->showCreate);
        $this->assertSame('Lenkungsausschuss', $c->form['name']);
        $this->assertSame('steering_committee', $c->form['type']);
        $this->assertSame('monatlich', $c->form['cadence']);
        $c->openCreate('strategy_meeting');
        $this->assertSame('strategy_meeting', $c->form['type']);
        $c->openCreate('unbekannt');
        $this->assertSame('', $c->form['name'], 'unbekannter Vorschlag = leeres Formular');
        $this->assertSame(0, \Platform\ManagementBook\Models\ManagementBookCommittee::where('management_book_id', $book2->id)->count(), 'keine Automatik');
    }

    public function test_committee_actions(): void
    {
        $d = $this->seedBook();
        $C = 'Platform\\ManagementBook\\Models\\ManagementBookCommittee';

        $c = $this->makeComponent(Committees::class, [$d['book']->id]);
        $c->openCreate();
        $c->form['name'] = 'Beirat';
        $c->form['type'] = 'board';
        $c->form['chair_user_id'] = $d['user']->id;
        $c->create();
        $beirat = $C::where('name', 'Beirat')->first();
        $this->assertNotNull($beirat);
        $this->assertSame(1, $beirat->members()->count(), 'Vorsitz wird Mitglied');

        $t = $this->makeComponent(CommitteeShow::class, [$d['committee']]);
        $t->openEdit();
        $t->form['cadence'] = 'zweiwöchentlich';
        $t->save();
        $this->assertSame('zweiwöchentlich', $d['committee']->fresh()->cadence);
        $t->setStatus('dormant');
        $this->assertSame('dormant', $d['committee']->fresh()->status->value);

        $t->openMember();
        $t->memberForm = ['user_id' => $d['agent']->id, 'role' => 'guest'];
        $t->addMember();
        $this->assertSame('guest', $d['committee']->members()->where('agent_id', $d['agent']->id)->orWhere('user_id', $d['agent']->id)->first()->role->value);

        $t->openMeeting();
        $t->meetingForm['title'] = 'LA Januar';
        $t->meetingForm['scheduled_at'] = now()->addMonth()->format('Y-m-d\TH:i');
        $t->createMeeting();
        $this->assertTrue($d['committee']->meetings()->where('title', 'LA Januar')->exists());

        $decision = $d['past']->decisions()->where('status', 'open')->first();
        $t->setDecisionStatus($decision->id, 'implemented');
        $this->assertSame('implemented', $decision->fresh()->status->value);

        $member = $d['committee']->members()->first();
        $t->removeMember('m' . $member->id); // Book-Liste (Gremium ohne Knoten); mit Knoten: 'e<entity_id>'
        $this->assertNull($d['committee']->members()->find($member->id));
    }

    public function test_meeting_actions(): void
    {
        $d = $this->seedBook();
        $meeting = $d['next'];
        $t = $this->makeComponent(MeetingShow::class, [$meeting]);

        $t->setStatus('prepared');
        $this->assertSame('prepared', $meeting->fresh()->status->value);

        $t->openItem();
        $t->itemForm = array_merge($t->itemForm, [
            'title' => 'Fokusthemen konkurrieren', 'goal' => 'Beide Fokusthemen vorantreiben', 'obstacle' => 'Dieselbe Führungskraft',
            'local_solution' => 'Wochenplanung anpassen', 'system_support' => 'Priorität entscheiden',
        ]);
        $t->saveItem();
        $item = $meeting->agendaItems()->where('title', 'Fokusthemen konkurrieren')->first();
        $this->assertNotNull($item);
        $this->assertSame(2, $item->position);
        $this->assertSame('Priorität entscheiden', $item->system_support);

        $t->moveItem($item->id, -1);
        $this->assertSame(1, $item->fresh()->position);

        $t->toggleCheck($item->id, 'priorities_clearer');
        $t->toggleCheck($item->id, 'conflicts_decided');
        $t->toggleCheck($item->id, 'priorities_clearer');
        $this->assertSame(['conflicts_decided'], $item->fresh()->outcome_checks);
        $t->toggleCheck($item->id, 'bogus');
        $this->assertSame(['conflicts_decided'], $item->fresh()->outcome_checks);

        $t->openDecision(null, $item->id);
        $t->decisionForm['text'] = 'Thema A hat Vorrang';
        $t->decisionForm['due_date'] = '2027-01-31';
        $t->saveDecision();
        $dec = $meeting->decisions()->first();
        $this->assertSame($item->id, $dec->agenda_item_id);
        $this->assertSame('open', $dec->status->value);
        $t->setDecisionStatus($dec->id, 'discarded');
        $this->assertSame('discarded', $dec->fresh()->status->value);

        $t->adoptMembers();
        $this->assertSame(2, $meeting->attendees()->count());
        $t->adoptMembers();
        $this->assertSame(2, $meeting->attendees()->count(), 'kein Doppel');
        $att = $meeting->attendees()->first();
        $t->toggleAttended($att->id);
        $this->assertTrue($att->fresh()->attended);

        $t->openMinutes();
        $t->saveMinutes();
        $min = $meeting->minutes()->first();
        $this->assertStringContainsString('Fokusthemen konkurrieren', $min->content, 'Vorlage aus Agenda');
        $t->toggleMinutesApproval($min->id);
        $this->assertNotNull($min->fresh()->approved_at);
        $t->toggleMinutesApproval($min->id);
        $this->assertNull($min->fresh()->approved_at);

        $t->openDocument();
        $t->documentForm = ['title' => 'Fokusliste', 'type' => '', 'agenda_item_id' => $item->id];
        $t->saveDocument();
        $this->assertTrue($meeting->documents()->where('title', 'Fokusliste')->where('agenda_item_id', $item->id)->exists());

        // fremde Sitzung: Kind gehört nicht hierher
        $foreign = $d['past']->agendaItems()->first();
        try {
            $t->deleteItem($foreign->id);
        } catch (\Throwable $e) {
        }
        $this->assertNotNull($foreign->fresh(), 'Punkt einer anderen Sitzung bleibt');

        $t->deleteItem($item->id);
        $this->assertNull($meeting->agendaItems()->find($item->id));
    }

    public function test_foreign_user_cannot_edit(): void
    {
        $d = $this->seedBook();
        $other = \Platform\Core\Models\User::create(['name' => 'O', 'email' => 'o@x.test', 'password' => 'x']);
        $this->actingAs($other);
        $this->withExceptionHandling();
        $this->get(route('management-book.meetings.show', $d['past']))->assertForbidden();
        $this->get(route('management-book.committees.show', $d['committee']))->assertForbidden();
    }
}
