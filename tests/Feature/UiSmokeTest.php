<?php

namespace Tests\Feature;

require_once __DIR__ . '/UiTestHarness.php';

use Tests\TestCase;

/**
 * UI-Smoke-Test: rendert alle Seiten und Bereiche des Moduls mit Testdaten als echte HTTP-Requests
 * (inkl. Layout, Livewire-Kindkomponenten). Aufruf: tests/run-in-taiste.sh tests/Feature/UiSmokeTest.php
 */
class UiSmokeTest extends TestCase
{
    use UiTestHarness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootModule();
        $this->withoutExceptionHandling();
    }

    public function test_book_show_all_tabs_render(): void
    {
        $d = $this->seedBook();
        foreach (['overview', 'book', 'strategy', 'risks', 'committees'] as $tab) {
            $this->get(route('management-book.books.show', $d['book']) . '?tab=' . $tab)
                ->assertOk()
                ->assertSee('Management Book 2027');
        }
    }

    public function test_dashboard_committee_meeting_render(): void
    {
        $d = $this->seedBook();
        // Book muss aus dem Dashboard per echtem Link erreichbar sein (nicht nur per onclick)
        $this->get(route('management-book.dashboard'))->assertOk()->assertSee('Management Book 2027')
            ->assertSee('href="' . route('management-book.books.show', $d['book']) . '"', false);
        $this->get(route('management-book.committees.show', $d['committee']))->assertOk();
        $this->get(route('management-book.meetings.show', $d['past']))->assertOk();
        $this->get(route('management-book.meetings.show', $d['next']))->assertOk();
    }
}
