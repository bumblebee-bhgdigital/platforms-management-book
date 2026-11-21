<?php

namespace Tests\Feature;

require_once __DIR__ . '/UiTestHarness.php';

use Tests\TestCase;

/**
 * Präsentationen (Slides-Decks) an Sitzung und Gremium.
 * Mit Slides:  SLIDES_MODULE_PATH=<slides-modul> tests/run-in-taiste.sh tests/Feature/UiSlidesIntegrationTest.php
 * Ohne Slides: tests/run-in-taiste.sh tests/Feature/UiSlidesIntegrationTest.php (Abschnitt bleibt ausgeblendet)
 */
class UiSlidesIntegrationTest extends TestCase
{
    use UiTestHarness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootModule();
        $this->withoutExceptionHandling();
    }

    private function slides(): bool
    {
        return \Platform\ManagementBook\Livewire\LinkedDecks::available();
    }

    private function deckComponent(string $type, int $id, bool $compact = false): \Platform\ManagementBook\Livewire\LinkedDecks
    {
        $c = app(\Platform\ManagementBook\Livewire\LinkedDecks::class);
        $c->setId('t' . uniqid());
        $c->setName('management-book.linked-decks');
        $c->mount($type, $id, $compact);

        return $c;
    }

    public function test_morph_aliases_registered(): void
    {
        $this->assertSame(\Platform\ManagementBook\Models\ManagementBookMeeting::class,
            \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel('management_book_meeting'));
        $this->assertSame(\Platform\ManagementBook\Models\ManagementBookCommittee::class,
            \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel('management_book_committee'));
    }

    public function test_pages_render_with_or_without_slides(): void
    {
        $d = $this->seedBook();

        $meetingHtml = $this->get(route('management-book.meetings.show', $d['past']))->assertOk()->getContent();
        $committeeHtml = $this->get(route('management-book.committees.show', $d['committee']))->assertOk()->getContent();

        if (! $this->slides()) {
            $this->assertStringNotContainsString('slides.*', $meetingHtml, 'ohne Slides kein Präsentations-Abschnitt');
            $this->assertStringNotContainsString('Noch keine Präsentation am Gremium', $committeeHtml);

            return;
        }

        $this->assertStringContainsString('Präsentationen', $meetingHtml);
        $this->assertStringContainsString('Noch keine Präsentation', $meetingHtml);
        $this->assertStringContainsString('Noch keine Präsentation am Gremium', $committeeHtml);
    }

    public function test_create_link_unlink_and_preview(): void
    {
        if (! $this->slides()) {
            $this->markTestSkipped('Slides-Modul nicht geladen (SLIDES_MODULE_PATH).');
        }
        $d = $this->seedBook();
        $meeting = $d['past'];
        $alias = \Platform\ManagementBook\Models\ManagementBookMeeting::MORPH_ALIAS;

        // Anlegen über die Komponente: Deck + Link
        $c = $this->deckComponent($alias, $meeting->id);
        $c->openCreate();
        $this->assertSame('LA Oktober', $c->newTitle, 'Titel aus der Sitzung vorbelegt');
        $c->newTitle = 'LA Oktober – Folien';
        $c->createDeck();
        $link = \Platform\Slides\Models\SlidesDeckLink::where('linkable_type', $alias)->where('linkable_id', $meeting->id)->first();
        $this->assertNotNull($link);
        $deck = \Platform\Slides\Models\SlidesDeck::find($link->deck_id);
        $this->assertSame('LA Oktober – Folien', $deck->title);

        // Folie schreiben (wie ein Agent) und Vorschau prüfen
        app(\Platform\Slides\Services\DeckService::class)->upsertSlides($deck, [
            ['key' => 'cover', 'html' => '<section id="cover"><h1>Kapazität Dezember</h1><script>alert(1)</script></section>', 'notes' => 'Einstieg'],
        ]);
        $html = $this->get(route('management-book.meetings.show', $meeting))->assertOk()->getContent();
        $this->assertStringContainsString('LA Oktober – Folien', $html);
        $this->assertStringContainsString('1 Folie', $html);
        $this->assertStringContainsString('Präsentieren', $html);
        $this->assertStringContainsString('sandbox="allow-same-origin"', $html);
        $this->assertStringContainsString('Kapazität Dezember', $html);
        $this->assertStringNotContainsString('alert(1)', $html, 'Sanitizer greift auch in der Vorschau');

        // Lösen und wieder verknüpfen
        $c->unlinkDeck($deck->id);
        $this->assertSame(0, \Platform\Slides\Models\SlidesDeckLink::where('linkable_type', $alias)->where('linkable_id', $meeting->id)->count());
        $this->assertNotNull(\Platform\Slides\Models\SlidesDeck::find($deck->id), 'Deck bleibt beim Lösen erhalten');
        $c->openLink();
        $c->linkDeck($deck->id);
        $this->assertSame(1, \Platform\Slides\Models\SlidesDeckLink::where('linkable_type', $alias)->where('linkable_id', $meeting->id)->count());

        // Gremium: kompakt, eigenes Deck
        $calias = \Platform\ManagementBook\Models\ManagementBookCommittee::MORPH_ALIAS;
        $cc = $this->deckComponent($calias, $d['committee']->id, true);
        $cc->openCreate();
        $cc->createDeck();
        $chtml = $this->get(route('management-book.committees.show', $d['committee']))->assertOk()->getContent();
        $this->assertStringContainsString('Lenkungsausschuss', $chtml);
        $this->assertStringContainsString('Noch keine Folien', $chtml);
    }

    public function test_foreign_user_cannot_mount(): void
    {
        if (! $this->slides()) {
            $this->markTestSkipped('Slides-Modul nicht geladen (SLIDES_MODULE_PATH).');
        }
        $d = $this->seedBook();
        $other = \Platform\Core\Models\User::create(['name' => 'Fremd', 'email' => 'f@x.test', 'password' => 'x']);
        $this->actingAs($other);
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->deckComponent(\Platform\ManagementBook\Models\ManagementBookMeeting::MORPH_ALIAS, $d['past']->id);
    }
}
