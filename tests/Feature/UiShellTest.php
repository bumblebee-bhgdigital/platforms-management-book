<?php

namespace Tests\Feature;

require_once __DIR__ . '/UiTestHarness.php';

use Tests\TestCase;

/**
 * Book-Shell (breites Layout, Orientierungsspalte, Erste Schritte) und Dashboard-Einstieg.
 * Aufruf: tests/run-in-taiste.sh tests/Feature/UiShellTest.php
 */
class UiShellTest extends TestCase
{
    use UiTestHarness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootModule();
        $this->withoutExceptionHandling();
    }

    public function test_book_shell_shows_rail_with_first_steps_and_guide_per_tab(): void
    {
        $d = $this->seedBook();
        $url = route('management-book.books.show', $d['book']);

        $this->get($url)->assertOk()
            ->assertSee('Erste Schritte')
            ->assertSee('Mit einem Knoten verknüpfen')
            ->assertSee('Wo steht dieses Book im Regelkreis?')
            ->assertSee('Unterwegs nach')
            ->assertSee('2030');

        $expect = [
            'book' => 'Sprache und Gedächtnis der Organisation',
            'strategy' => 'Fokussieren heißt verzichten.',
            'risks' => 'Wissen, was uns treffen kann',
            'committees' => 'Er macht sie möglich.',
        ];
        foreach ($expect as $tab => $text) {
            $this->get($url . '?tab=' . $tab)->assertOk()->assertSee($text);
        }
    }

    public function test_first_steps_reflect_data(): void
    {
        $d = $this->seedBook();
        $steps = collect(app(\Platform\ManagementBook\Services\BookInsights::class)->firstSteps($d['book']))->keyBy('key');

        $this->assertCount(7, $steps);
        $this->assertFalse($steps['node']['done'], 'Seed-Book ist nicht verknüpft');
        $this->assertTrue($steps['mission_vision']['done']);
        $this->assertFalse($steps['current_situation']['done']);
        $this->assertFalse($steps['focus_areas']['done'], 'nur 2 Fokusräume');
        $this->assertStringContainsString('Bisher 2', $steps['focus_areas']['hint']);
        $this->assertTrue($steps['risks']['done'], 'bewertete Risiken + Risikobereitschaft');
        $this->assertTrue($steps['steering']['done']);
        $this->assertTrue($steps['first_meeting']['done'], 'Agenda-Punkt mit Leitfaden vorhanden');
        foreach ($steps as $s) {
            $this->assertArrayHasKey($s['tab'], \Platform\ManagementBook\Livewire\Show::TABS);
        }
    }

    public function test_dashboard_intro_rail_and_book_link(): void
    {
        $d = $this->seedBook();
        $this->get(route('management-book.dashboard'))->assertOk()
            ->assertSee('Orientierung, die nicht an einer Person hängt')
            ->assertSee('Der Regelkreis in Kürze')
            ->assertSee('Vertiefen in der Academy')
            ->assertSee('href="' . route('management-book.books.show', $d['book']) . '"', false)
            ->assertSee('Unterwegs nach 2030');
    }

    public function test_dashboard_empty_state_explains_how_a_book_emerges(): void
    {
        $U = \Platform\Core\Models\User::class;
        $user = $U::create(['name' => 'Neu', 'email' => 'neu@x.test', 'password' => 'x']);
        $teamId = \Illuminate\Support\Facades\DB::table('teams')->insertGetId(['name' => 'T', 'user_id' => $user->id, 'personal_team' => false, 'created_at' => now(), 'updated_at' => now()]);
        \Illuminate\Support\Facades\DB::table('team_user')->insert(['team_id' => $teamId, 'user_id' => $user->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);
        $user->forceFill(['current_team_id' => $teamId])->save();
        $this->actingAs($user->fresh());

        $this->get(route('management-book.dashboard'))->assertOk()
            ->assertSee('So entsteht ein Management Book')
            ->assertSee('5–7 Fokusräume mit Meilensteinen festlegen');
    }
}
