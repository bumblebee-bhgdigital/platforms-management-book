<?php

namespace Tests\Feature;

require_once __DIR__ . '/UiTestHarness.php';

use Platform\ManagementBook\Livewire\Book\Risks;
use Platform\ManagementBook\Models\ManagementBookRisk;
use Tests\TestCase;

/** Risiken-Tab: Render (HTTP) und Aktionen (Livewire). Aufruf: tests/run-in-taiste.sh tests/Feature/UiRisksTest.php */
class UiRisksTest extends TestCase
{
    use UiTestHarness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootModule();
        $this->withoutExceptionHandling();
    }

    public function test_risks_tab_renders(): void
    {
        $d = $this->seedBook();
        $this->get(route('management-book.books.show', $d['book']) . '?tab=risks')
            ->assertOk()
            ->assertSee('Lieferengpass bei Frischeprodukten')
            ->assertSee('Moderates Risiko')
            ->assertSee('Risikoregister')
            ->assertSee('Wahl bei mehreren Optionen')
            ->assertSee('Maßnahmen umsetzen, überwachen')
            ->assertDontSee('Erledigt'); // geschlossenes Risiko standardmäßig ausgeblendet
    }

    public function test_empty_state_renders(): void
    {
        $d = $this->seedBook();
        ManagementBookRisk::where('management_book_id', $d['book']->id)->forceDelete();
        $this->get(route('management-book.books.show', $d['book']) . '?tab=risks')
            ->assertOk()
            ->assertSee('Noch keine Risiken erfasst');
    }

    /** Livewire::test() liefert in der Verifikations-App keinen Snapshot; daher Komponente direkt bedienen. */
    private function risksComponent(int $bookId): Risks
    {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $c = new Risks();
        $c->mount($bookId);

        return $c;
    }

    private function saveFails(Risks $c, string $field): void
    {
        try {
            $c->save();
            $this->fail("Validierung von {$field} erwartet");
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_create_edit_close_delete_and_appetite(): void
    {
        $d = $this->seedBook();
        $bookId = $d['book']->id;

        // Anlegen mit Validierung
        $c = $this->risksComponent($bookId);
        $c->openCreate();
        $this->assertSame('10', $c->form['number']);
        $this->assertTrue($c->showModal);
        $c->form['title'] = '';
        $this->saveFails($c, 'form.title');
        $c->form['title'] = 'x';
        $c->form['probability'] = 6;
        $this->saveFails($c, 'form.probability');

        $c = $this->risksComponent($bookId);
        $c->openCreate();
        $c->form = array_merge($c->form, [
            'title' => 'Cyberangriff', 'probability' => '5', 'impact' => '5', 'category' => 'IT-Risiko',
            'owner_user_id' => (string) $d['user']->id, 'due_date' => '2027-01-31', 'organization_entity_id' => 999999,
        ]);
        $c->save();
        $this->assertNull(ManagementBookRisk::where('title', 'Cyberangriff')->first(), 'unbekannter Knoten abgelehnt');
        $c->form['organization_entity_id'] = null;
        $c->save();
        $this->assertFalse($c->showModal);

        $risk = ManagementBookRisk::where('title', 'Cyberangriff')->firstOrFail();
        $this->assertSame(25, $risk->score);
        $this->assertSame('critical', $risk->level()->value);
        $this->assertSame('10', $risk->number);
        $this->assertSame($d['user']->id, $risk->owner_user_id);
        $this->assertSame('2027-01-31', $risk->due_date->toDateString());
        $this->assertSame('open', $risk->status->value);

        // Render mit offenem Modal (Vorschau, Knoten- und User-Auswahl)
        $c = $this->risksComponent($bookId);
        $c->openEdit($risk->id);
        $this->assertSame('Cyberangriff', $c->form['title']);
        $html = $c->render()->render();
        $this->assertStringContainsString('Risiko bearbeiten', $html);
        $this->assertStringContainsString('Sofortiges Handeln erforderlich', $html);

        // Bearbeiten: Score neu
        $c->form['impact'] = '2';
        $c->form['measures_implemented'] = 'Backup getestet';
        $c->save();
        $risk->refresh();
        $this->assertSame(10, $risk->score);
        $this->assertSame('Backup getestet', $risk->measures_implemented);
        $this->assertSame('IT-Risiko', $risk->category, 'unverändert');

        // Heatmap-Filter an/aus + Register gefiltert
        $c = $this->risksComponent($bookId);
        $c->filterCell(4, 3);
        $this->assertSame([4, 3], $c->cell);
        $this->assertSame(1, $c->render()->getData()['risks']->count());
        $c->filterCell(4, 3);
        $this->assertNull($c->cell);
        $data = $c->render()->getData();
        $this->assertSame(5, $data['risks']->count(), '4 Seed + Cyberangriff, ohne geschlossenes');
        $this->assertSame(20, $data['risks']->first()->score, 'höchster Score zuerst');
        $this->assertSame(1, $data['unrated']);
        $c->levelFilter = 'critical';
        $this->assertSame(1, $c->render()->getData()['risks']->count());
        $c->clearFilters();
        $c->showClosed = true;
        $this->assertSame(6, $c->render()->getData()['risks']->count());

        // Schließen und Löschen
        $this->risksComponent($bookId)->closeRisk($risk->id);
        $this->assertSame('closed', $risk->fresh()->status->value);
        $this->risksComponent($bookId)->deleteRisk($risk->id);
        $this->assertNull(ManagementBookRisk::find($risk->id));
        $this->assertNotNull(ManagementBookRisk::withTrashed()->find($risk->id));

        // Risikobereitschaft
        $this->risksComponent($bookId)->setAppetite('high');
        $this->assertSame('high', $d['book']->fresh()->risk_appetite->value);
        $this->risksComponent($bookId)->setAppetite('bogus');
        $this->assertSame('high', $d['book']->fresh()->risk_appetite->value);
        $this->risksComponent($bookId)->setAppetite('');
        $this->assertNull($d['book']->fresh()->risk_appetite);
    }

    public function test_foreign_user_cannot_view_or_edit(): void
    {
        $d = $this->seedBook();
        $other = \Platform\Core\Models\User::create(['name' => 'O', 'email' => 'o@x.test', 'password' => 'x']);
        $this->actingAs($other);
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->risksComponent($d['book']->id);
    }
}
