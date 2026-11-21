<?php

namespace Tests\Feature;

require_once __DIR__ . '/UiTestHarness.php';

use Platform\ManagementBook\Livewire\Book\Strategy;
use Platform\ManagementBook\Models\ManagementBookFocusArea;
use Platform\ManagementBook\Models\ManagementBookForecast;
use Platform\ManagementBook\Models\ManagementBookMilestone;
use Platform\ManagementBook\Models\ManagementBookObstacle;
use Tests\TestCase;

/**
 * Strategie-Tab: Render (Fokusräume, Transformation Map, Regnose) per HTTP und Aktionen direkt an der
 * Komponente (Livewire::test rendert in der Verifikations-App nicht).
 * Aufruf: tests/run-in-taiste.sh tests/Feature/UiStrategyTest.php
 */
class UiStrategyTest extends TestCase
{
    use UiTestHarness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootModule();
        $this->withoutExceptionHandling();
    }

    private function strategy(int $bookId): Strategy
    {
        $c = new Strategy();
        $c->mount($bookId);

        return $c;
    }

    private function assertInvalid(callable $fn, string $field): void
    {
        try {
            $fn();
            $this->fail("Validierungsfehler für {$field} erwartet");
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_renders_all_sections(): void
    {
        $d = $this->seedBook();
        $url = route('management-book.books.show', $d['book']) . '?tab=strategy';

        $this->get($url)->assertOk()
            ->assertSee('Eventgeschäft')->assertSee('Digitalisierung')
            ->assertSee('Ausgebuchte Locations')->assertSee('Personalengpass')->assertSee('Zweite Küche')
            ->assertSee('überfällig')               // Eventteam aufgebaut: Q4 Vorjahr, in Arbeit
            ->assertSee('Woran erkennen wir')        // Default-Leitfrage
            ->assertSee('Wenige Themen, die wirklich tragen');  // Einstieg Fokusräume

        $this->get($url . '&view=map')->assertOk()
            ->assertSee('Fortschritt')->assertSee('Ohne Termin')->assertSee('Nächster Waypoint')
            ->assertSee('Plattform live')->assertSee((string) now()->year)
            ->assertSee('Der Weg über die Zeit')->assertSee('So liest man die Map');

        $this->get($url . '&view=regnose')->assertOk()
            ->assertSee('Regnose 2030')->assertSee('Zieljahr 2030')->assertSee('Eventgeschäft')
            ->assertSee('Aus der Zukunft zurückdenken');
    }

    public function test_empty_state(): void
    {
        $d = $this->seedBook();
        ManagementBookFocusArea::where('management_book_id', $d['book']->id)->get()->each->delete();
        $this->get(route('management-book.books.show', $d['book']) . '?tab=strategy')
            ->assertOk()->assertSee('Noch keine Fokusräume')
            ->assertSee('Eventgeschäft ausbauen')->assertSee('Unsere Locations sind an 200 Tagen ausgebucht')
            ->assertSee('Zweite Küche in Betrieb (Q2/2027)');
    }

    public function test_focus_area_crud_and_order(): void
    {
        $d = $this->seedBook();
        $book = $d['book'];

        $c = $this->strategy($book->id);
        $c->openFocusArea();
        $c->faForm['title'] = '';
        $this->assertInvalid(fn () => $c->saveFocusArea(), 'faForm.title');
        $c->faForm['title'] = 'Neuer Fokus';
        $c->faForm['forecast_id'] = $d['forecast']->id;
        $c->faForm['central_question_obstacles'] = 'Was bremst?';
        $c->saveFocusArea();
        $this->assertFalse($c->showFocusArea);

        $new = ManagementBookFocusArea::where('title', 'Neuer Fokus')->firstOrFail();
        $this->assertSame($d['forecast']->id, $new->forecast_id);
        $this->assertSame('Was bremst?', $new->central_question_obstacles);
        $this->assertSame(3, $new->order);

        $this->strategy($book->id)->moveFocusArea($new->id, -1);
        $this->assertSame(['Eventgeschäft', 'Neuer Fokus', 'Digitalisierung'],
            ManagementBookFocusArea::where('management_book_id', $book->id)->orderBy('order')->pluck('title')->all());

        $c = $this->strategy($book->id);
        $c->openFocusArea($new->id);
        $this->assertSame('Neuer Fokus', $c->faForm['title']);
        $c->faForm['title'] = 'Umbenannt';
        $c->saveFocusArea();
        $this->assertSame('Umbenannt', $new->fresh()->title);

        $this->strategy($book->id)->deleteFocusArea($new->id);
        $this->assertSoftDeleted($new);
    }

    public function test_items_and_milestone_status(): void
    {
        $d = $this->seedBook();
        $book = $d['book'];

        $c = $this->strategy($book->id);
        $c->openItem('obstacle', $d['fa']->id);
        $c->itemForm['title'] = 'Fachkräftemangel';
        $c->saveItem();
        $this->assertTrue(ManagementBookObstacle::where('title', 'Fachkräftemangel')->where('focus_area_id', $d['fa']->id)->exists());

        $c = $this->strategy($book->id);
        $c->openItem('milestone', $d['fa2']->id);
        $c->itemForm['title'] = 'Pilotkunde';
        $c->itemForm['target_year'] = 2031;
        $c->itemForm['target_quarter'] = 5;
        $this->assertInvalid(fn () => $c->saveItem(), 'itemForm.target_quarter');
        $c->itemForm['target_quarter'] = 3;
        $c->itemForm['status'] = 'bogus';
        $this->assertInvalid(fn () => $c->saveItem(), 'itemForm.status');
        $c->itemForm['status'] = 'in_progress';
        $c->saveItem();
        $m = ManagementBookMilestone::where('title', 'Pilotkunde')->firstOrFail();
        $this->assertSame([2031, 3, 'in_progress'], [$m->target_year, $m->target_quarter, $m->status->value]);

        // Status per Klick: reached setzt reached_at, Zurücksetzen leert es (Model-Hook)
        $this->strategy($book->id)->setMilestoneStatus($m->id, 'reached');
        $this->assertNotNull($m->fresh()->reached_at);
        $this->strategy($book->id)->setMilestoneStatus($m->id, 'planned');
        $this->assertNull($m->fresh()->reached_at);

        // Verschieben in anderen Fokusraum + Reihenfolge + Löschen
        $c = $this->strategy($book->id);
        $c->openItem('milestone', $d['fa2']->id, $m->id);
        $c->itemForm['focus_area_id'] = $d['fa']->id;
        $c->saveItem();
        $this->assertSame($d['fa']->id, $m->fresh()->focus_area_id);
        $this->strategy($book->id)->moveItem('milestone', $m->id, -1);
        $this->strategy($book->id)->deleteItem('milestone', $m->id);
        $this->assertSoftDeleted($m);
    }

    public function test_forecast_and_versions(): void
    {
        $d = $this->seedBook();
        $book = $d['book'];

        $c = $this->strategy($book->id);
        $c->openForecast();
        $c->forecastForm['title'] = 'Regnose 2035';
        $c->forecastForm['target_year'] = 2035;
        $c->forecastForm['content'] = 'Wir sehen …';
        $c->saveForecast();
        $f = ManagementBookForecast::where('title', 'Regnose 2035')->firstOrFail();
        $this->assertSame('2035-12-31', $f->target_date->toDateString());
        $this->assertSame(1, $f->versions()->count());
        $this->assertNotNull($f->current_version_id);

        $c = $this->strategy($book->id);
        $c->openVersion($f->id);
        $c->versionForm['content'] = '';
        $this->assertInvalid(fn () => $c->saveVersion(), 'versionForm.content');
        $c->versionForm['content'] = 'Wir sehen mehr …';
        $c->versionForm['change_note'] = 'Workshop';
        $c->saveVersion();
        $f->refresh();
        $this->assertSame(2, $f->versions()->count());
        $this->assertSame('Wir sehen mehr …', $f->content);
        $this->assertSame(2, $f->currentVersion->version);

        $this->strategy($book->id)->deleteForecast($d['forecast']->id);
        $this->assertSoftDeleted($d['forecast']);
        $this->assertNull($d['fa']->fresh()->forecast_id, 'Zuordnung gelöst');
    }

    public function test_foreign_user_cannot_view_or_edit(): void
    {
        $d = $this->seedBook();
        $other = \Platform\Core\Models\User::create(['name' => 'O', 'email' => 'o@x.test', 'password' => 'x']);
        $this->actingAs($other);
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->strategy($d['book']->id);
    }
}
