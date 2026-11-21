<?php

namespace Tests\Feature;

require_once __DIR__ . '/UiTestHarness.php';

use Platform\ManagementBook\Enums\StrategicDocumentType;
use Platform\ManagementBook\Models\ManagementBookStrategicDocument;
use Platform\ManagementBook\Services\BookInsights;
use Tests\TestCase;

/**
 * Übersicht (Regelkreis/Cockpit), Kapitel und Dashboard.
 * Aufruf: tests/run-in-taiste.sh tests/Feature/UiOverviewTest.php
 */
class UiOverviewTest extends TestCase
{
    use UiTestHarness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootModule();
        $this->withoutExceptionHandling();
    }

    public function test_insights(): void
    {
        $d = $this->seedBook();
        $i = app(BookInsights::class)->forBook($d['book']->fresh());

        $this->assertSame(1, $i['risks']['by_level']['critical'], 'E5×A4=20');
        $this->assertSame(1, $i['risks']['by_level']['high'], 'E4×A3=12');
        $this->assertSame(1, $i['risks']['by_level']['low'], 'E2×A2=4');
        $this->assertSame(1, $i['risks']['unrated']);
        $this->assertSame(4, $i['risks']['open'], 'geschlossenes zählt nicht');
        $this->assertSame(1, $i['risks']['matrix'][5][4]);
        $this->assertSame(4, $i['milestones']['total']);
        $this->assertSame(1, $i['milestones']['reached']);
        $this->assertSame(1, $i['milestones']['overdue']->count(), 'Q4 Vorjahr in Arbeit');
        $this->assertSame(2, $i['focus_areas']);
        $this->assertSame(['filled' => 3, 'total' => 9], $i['chapters']);
        $this->assertSame('LA Dezember', $i['next_meeting']->title);
        $this->assertSame(1, $i['meetings_last_90']);
        $this->assertSame(1, $i['open_decisions']);
        $this->assertSame(1, $i['overdue_decisions']->count());

        $cycle = collect($i['cycle'])->keyBy('key');
        $this->assertCount(8, $cycle);
        $this->assertSame('open', $cycle['strategy_meeting']['state'], 'Gremium ohne Sitzung');
        $this->assertSame('untracked', $cycle['workshop']['state']);
        $this->assertSame('active', $cycle['book']['state']);
        $this->assertSame('active', $cycle['focus']['state'], '2 Fokusräume');
        $this->assertSame('attention', $cycle['execution']['state'], 'überfälliger Meilenstein');
        $this->assertSame('active', $cycle['learning']['state'], 'LA vor 3 Wochen');
        $this->assertSame('active', $cycle['feedback']['state'], 'Review in einem Monat');

        $all = app(BookInsights::class)->forBooks(collect([$d['book']->fresh()]));
        $this->assertSame(1, $all['risks_critical']);
        $this->assertSame(1, $all['risks_high']);
        $this->assertSame(1, $all['upcoming_meetings']->count());
        $this->assertSame(1, $all['overdue_decisions']);
        $this->assertSame(20, $all['top_risks']->first()->score);
    }

    public function test_overview_renders(): void
    {
        $d = $this->seedBook();
        $this->get(route('management-book.books.show', $d['book']))
            ->assertOk()
            ->assertSee('Regelkreis')
            ->assertSee('Strategiemeeting GF')
            ->assertSee('Rückkopplung')
            ->assertSee('Wir machen Gastfreundschaft verlässlich.')
            ->assertSee('LA Dezember')
            ->assertSee('Zusatzschicht im Dezember')
            ->assertSee('Eventteam aufgebaut')
            ->assertSee('Moderates Risiko');
    }

    public function test_station_selection(): void
    {
        $d = $this->seedBook();
        $o = new \Platform\ManagementBook\Livewire\Book\Overview();
        $o->mount($d['book']->id);
        $o->selectStation(6);
        $this->assertSame(6, $o->station);
        $o->selectStation(42);
        $this->assertSame(7, $o->station);
    }

    public function test_chapters_render_and_versioning(): void
    {
        $d = $this->seedBook();
        $this->get(route('management-book.books.show', $d['book']) . '?tab=book')
            ->assertOk()
            ->assertSee('Woher kommen wir?')
            ->assertSee('Nach welchen Grundsätzen führen und entscheiden wir?')
            ->assertSee('Hilfe statt Macht')
            ->assertSee('3 / 9');

        $c = new \Platform\ManagementBook\Livewire\Book\Chapters();
        $c->mount($d['book']->id);
        $c->edit('origin');
        $this->assertTrue($c->showEdit);
        $c->form['content'] = 'Gegründet 1990 als Familienbetrieb.';
        $c->form['change_note'] = 'Erstfassung';
        $c->save();
        $this->assertFalse($c->showEdit);
        $origin = ManagementBookStrategicDocument::activeFor($d['book']->id, StrategicDocumentType::ORIGIN);
        $this->assertSame('Gegründet 1990 als Familienbetrieb.', $origin->content);
        $this->assertSame(1, $origin->version);

        // unverändert speichern -> keine neue Version
        $c->edit('origin');
        $c->save();
        $this->assertSame(1, ManagementBookStrategicDocument::where('management_book_id', $d['book']->id)->ofType('origin')->count());

        // geänderte Mission -> Version 2, alte inaktiv
        $c->edit('mission');
        $c->form['content'] = 'Neu formuliert.';
        $c->save();
        $this->assertSame(2, ManagementBookStrategicDocument::activeFor($d['book']->id, StrategicDocumentType::MISSION)->version);
        $this->assertSame(1, ManagementBookStrategicDocument::where('management_book_id', $d['book']->id)->ofType('mission')->where('is_active', true)->count());

        $c->history('mission');
        $this->assertTrue($c->showHistory);
        $c->render(); // Verlauf lädt Versionen
        $this->get(route('management-book.books.show', $d['book']) . '?tab=book')->assertSee('Gegründet 1990');

        // fremder User darf nicht bearbeiten
        $other = \Platform\Core\Models\User::create(['name' => 'O', 'email' => 'o@x.test', 'password' => 'x']);
        $this->actingAs($other);
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $chap = new \Platform\ManagementBook\Livewire\Book\Chapters();
        $chap->bookId = $d['book']->id;
        $chap->edit('origin');
    }

    public function test_dashboard_cockpit(): void
    {
        $this->seedBook();
        $this->get(route('management-book.dashboard'))
            ->assertOk()
            ->assertSee('Kritische / hohe Risiken')
            ->assertSee('Nächste Sitzungen')
            ->assertSee('LA Dezember')
            ->assertSee('Zusatzschicht im Dezember')
            ->assertSee('Ausfall Schlüsselpersonal')
            ->assertSee('Management Book 2027');
    }
}
