<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

/**
 * Gemeinsames Setup für die UI-Smoke-Tests (Livewire-Render gegen sqlite :memory:).
 * Läuft NUR in einer Verifikations-App (vendor/martin3r/*). Aufruf siehe UiSmokeTest.
 *
 * - lädt das Modul per Autoloader aus MB_MODULE_PATH
 * - spielt Core-, Organization- und Modul-Migrationen ein
 * - ergänzt JSON_UNQUOTE für sqlite (MySQL-Funktion, die die Organization nutzt)
 * - seedBook(): ein Book mit Strategie, Risiken, Gremium, Sitzungen und Sitzungs-Kindern
 */
trait UiTestHarness
{
    protected string $module;

    protected function bootModule(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        $this->module = getenv('MB_MODULE_PATH') ?: '/home/agent/agent-workspace/platforms-management-book';
        $module = $this->module;
        spl_autoload_register(function ($c) use ($module) {
            $p = 'Platform\\ManagementBook\\';
            if (str_starts_with($c, $p)) {
                $f = $module . '/src/' . str_replace('\\', '/', substr($c, strlen($p))) . '.php';
                if (is_file($f)) {
                    require $f;
                }
            }
        });

        DB::connection()->getPdo()->sqliteCreateFunction('JSON_UNQUOTE', fn ($v) => is_string($v) ? trim($v, '"') : $v, 1);

        $files = array_merge(
            glob(base_path('vendor/martin3r/platform-core/database/migrations/*.php')),
            glob(base_path('vendor/martin3r/platform-organization/database/migrations/*.php')),
            glob($module . '/database/migrations/*.php'),
        );
        usort($files, fn ($a, $b) => strcmp(basename($a), basename($b)));
        foreach ($files as $f) {
            try {
                (require $f)->up();
            } catch (\Throwable $e) {
                // Fremd-Migrationen, die auf sqlite nicht laufen, ignorieren
            }
        }
        $provider = $this->app->register(\Platform\ManagementBook\ManagementBookServiceProvider::class);
        // Livewire-Komponenten registrieren (in der App passiert das nur, wenn das Modul in der DB registriert ist)
        $m = new \ReflectionMethod($provider, 'registerLivewireComponents');
        $m->setAccessible(true);
        $m->invoke($provider);
        // Modul-Registrierung wie in der App (Routen hängen daran)
        if (! \Illuminate\Support\Facades\Route::has('management-book.dashboard')) {
            \Illuminate\Support\Facades\Route::middleware('web')->prefix('management-book')->group($module . '/routes/web.php');
        }
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();
        $this->app['view']->addNamespace('management-book', $module . '/resources/views');

        // Minimales Layout statt platform::layouts.app (das echte zieht OAuth, Terminal usw. nach)
        $layoutDir = sys_get_temp_dir() . '/mb-ui-layout';
        @mkdir($layoutDir . '/layouts', 0777, true);
        file_put_contents($layoutDir . '/layouts/app.blade.php', '<html><body>{{ $slot }}</body></html>');
        $this->app['view']->prependNamespace('platform', $layoutDir);
    }

    /** @return array<string,mixed> */
    protected function seedBook(): array
    {
        $U = \Platform\Core\Models\User::class;
        $user = $U::create(['name' => 'Martin', 'email' => 'm@x.test', 'password' => 'x']);
        $agent = $U::create(['name' => 'Agent', 'email' => 'a@x.test', 'password' => 'x']);
        $teamId = DB::table('teams')->insertGetId(['name' => 'T1', 'user_id' => $user->id, 'personal_team' => false, 'created_at' => now(), 'updated_at' => now()]);
        $team = \Platform\Core\Models\Team::find($teamId);
        DB::table('team_user')->insert(['team_id' => $teamId, 'user_id' => $user->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);
        $user->forceFill(['current_team_id' => $teamId])->save();
        $user = $user->fresh();
        $this->actingAs($user);

        $service = app(\Platform\ManagementBook\Services\ManagementBookService::class);
        $book = $service->create($user, $team, [
            'title' => 'Management Book 2027', 'description' => 'Verdichtete Orientierung', 'horizon_year' => 2030,
            'review_rhythm' => 'quarterly', 'next_review_at' => now()->addMonth()->toDateString(), 'risk_appetite' => 'moderate',
        ]);

        $M = 'Platform\\ManagementBook\\Models\\';
        $doc = fn ($type, $content) => ($M . 'ManagementBookStrategicDocument')::create([
            'team_id' => $teamId, 'management_book_id' => $book->id, 'type' => $type, 'title' => ucfirst($type),
            'content' => $content, 'is_active' => true, 'valid_from' => now()->toDateString(), 'created_by' => $user->id,
        ]);
        $doc('mission', 'Wir machen Gastfreundschaft verlässlich.');
        $doc('vision', '2030 sind wir die erste Adresse für Events im Rheinland.');
        $doc('principles', "- Hilfe statt Macht\n- Lokal vor übergreifend");

        $forecast = ($M . 'ManagementBookForecast')::create(['team_id' => $teamId, 'management_book_id' => $book->id, 'title' => 'Regnose 2030', 'target_date' => '2030-12-31', 'content' => 'Zukunftsbild', 'created_by' => $user->id]);
        $fa = ($M . 'ManagementBookFocusArea')::create(['team_id' => $teamId, 'management_book_id' => $book->id, 'forecast_id' => $forecast->id, 'title' => 'Eventgeschäft', 'order' => 1, 'created_by' => $user->id]);
        $fa2 = ($M . 'ManagementBookFocusArea')::create(['team_id' => $teamId, 'management_book_id' => $book->id, 'title' => 'Digitalisierung', 'order' => 2, 'created_by' => $user->id]);
        ($M . 'ManagementBookVisionImage')::create(['focus_area_id' => $fa->id, 'title' => 'Ausgebuchte Locations', 'created_by' => $user->id]);
        ($M . 'ManagementBookObstacle')::create(['focus_area_id' => $fa->id, 'title' => 'Personalengpass', 'created_by' => $user->id]);
        $ms1 = ($M . 'ManagementBookMilestone')::create(['focus_area_id' => $fa->id, 'title' => 'Zweite Küche', 'target_year' => (int) now()->year, 'target_quarter' => 1, 'status' => 'reached', 'reached_at' => now()->subMonth(), 'created_by' => $user->id]);
        ($M . 'ManagementBookMilestone')::create(['focus_area_id' => $fa->id, 'title' => 'Eventteam aufgebaut', 'target_year' => (int) now()->year - 1, 'target_quarter' => 4, 'status' => 'in_progress', 'created_by' => $user->id]);
        ($M . 'ManagementBookMilestone')::create(['focus_area_id' => $fa2->id, 'title' => 'Plattform live', 'target_year' => (int) now()->year + 1, 'target_quarter' => 2, 'created_by' => $user->id]);
        ($M . 'ManagementBookMilestone')::create(['focus_area_id' => $fa2->id, 'title' => 'Ohne Termin', 'created_by' => $user->id]);

        $R = $M . 'ManagementBookRisk';
        $R::create(['team_id' => $teamId, 'management_book_id' => $book->id, 'number' => '1', 'title' => 'Lieferengpass bei Frischeprodukten', 'probability' => 4, 'impact' => 3, 'category' => 'Operatives Risiko', 'measures_planned' => 'Alternative Lieferanten prüfen', 'owner_user_id' => $user->id, 'due_date' => now()->addMonths(2)->toDateString()]);
        $R::create(['team_id' => $teamId, 'management_book_id' => $book->id, 'number' => '2', 'title' => 'Ausfall Schlüsselpersonal', 'probability' => 5, 'impact' => 4, 'category' => 'Personalrisiko', 'status' => 'monitored']);
        $R::create(['team_id' => $teamId, 'management_book_id' => $book->id, 'number' => '3', 'title' => 'Wettbewerber senkt Preise', 'probability' => 2, 'impact' => 2]);
        $R::create(['team_id' => $teamId, 'management_book_id' => $book->id, 'title' => 'Unbewertet']);
        $R::create(['team_id' => $teamId, 'management_book_id' => $book->id, 'number' => '9', 'title' => 'Erledigt', 'probability' => 3, 'impact' => 3, 'status' => 'closed']);

        $committee = ($M . 'ManagementBookCommittee')::create(['team_id' => $teamId, 'management_book_id' => $book->id, 'name' => 'Lenkungsausschuss', 'type' => 'steering_committee', 'cadence' => 'monatlich', 'chair_user_id' => $user->id, 'created_by' => $user->id]);
        $committee->members()->create(['user_id' => $user->id, 'role' => 'chair']);
        $committee->members()->create(['agent_id' => $agent->id, 'role' => 'secretary']);
        ($M . 'ManagementBookCommittee')::create(['team_id' => $teamId, 'management_book_id' => $book->id, 'name' => 'Strategiemeeting GF', 'type' => 'strategy_meeting', 'created_by' => $user->id]);

        $Mt = $M . 'ManagementBookMeeting';
        $past = $Mt::create(['team_id' => $teamId, 'committee_id' => $committee->id, 'title' => 'LA Oktober', 'scheduled_at' => now()->subWeeks(3), 'status' => 'held', 'duration_minutes' => 90, 'location' => 'Raum 1']);
        $next = $Mt::create(['team_id' => $teamId, 'committee_id' => $committee->id, 'title' => 'LA Dezember', 'scheduled_at' => now()->addWeek(), 'status' => 'planned']);

        $item = $past->agendaItems()->create([
            'title' => 'Kapazität Dezember', 'position' => 1, 'owner_user_id' => $user->id, 'duration_minutes' => 20,
            'goal' => 'Drei Großaufträge liefern', 'observation' => 'Produktion ausgelastet', 'obstacle' => 'Stadion-Geschäft parallel',
            'local_solution' => 'Schichtplan umstellen', 'system_support' => 'Priorisierung zwischen Bereichen',
            'outcome_checks' => ['blockers_removed', 'priorities_clearer', 'conflicts_decided', 'responsibilities_clear', 'unit_self_reliant'],
        ]);
        $past->agendaItems()->create(['title' => 'Bericht Fokusthemen', 'position' => 2]);
        $past->decisions()->create(['text' => 'Zusatzschicht im Dezember', 'agenda_item_id' => $item->id, 'owner_user_id' => $user->id, 'due_date' => now()->subDays(3)->toDateString()]);
        $past->decisions()->create(['text' => 'Leihpersonal prüfen', 'status' => 'implemented']);
        $past->minutes()->create(['content' => "# Protokoll\n\nAlles besprochen.", 'approved_at' => now()->subWeeks(2), 'approved_by' => $user->id]);
        $past->attendees()->create(['user_id' => $user->id, 'role' => 'chair', 'attended' => true]);
        $past->attendees()->create(['agent_id' => $agent->id, 'role' => 'secretary', 'attended' => false]);
        $past->documents()->create(['title' => 'Kapazitätsplanung', 'type' => 'Vorlage', 'agenda_item_id' => $item->id]);
        $next->agendaItems()->create(['title' => 'Personalrisiko', 'position' => 1]);

        return compact('user', 'agent', 'team', 'teamId', 'book', 'committee', 'past', 'next', 'fa', 'fa2', 'forecast', 'ms1');
    }
}
