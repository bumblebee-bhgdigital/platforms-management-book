<?php

/**
 * Management Book Service Provider
 * 
 * Dieser Service Provider ist das Herzstück jedes Platform-Moduls.
 * 
 * WICHTIG FÜR LLMs:
 * - Dieser Service Provider folgt dem exakten Muster von HCM und Planner
 * - Alle wichtigen Schritte sind kommentiert
 * - Config wird in register() geladen (Laravel Best Practice)
 * - Modul-Registrierung erfolgt in boot()
 * 
 * ANPASSUNGEN FÜR NEUES MODUL:
 * 1. Ersetze "ManagementBook" durch deinen Modul-Namen (PascalCase)
 * 2. Ersetze "management-book" durch deinen Modul-Namen (kebab-case)
 * 3. Passe Namespaces an
 * 4. Füge Commands/Tools hinzu falls nötig
 * 
 * @see Platform\Core\PlatformCore für Modul-Registrierung
 * @see Platform\Core\Routing\ModuleRouter für Route-Registrierung
 */

namespace Platform\ManagementBook;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ManagementBookServiceProvider extends ServiceProvider
{
    /**
     * Register Services
     * 
     * Wird VOR boot() aufgerufen.
     * Hier sollten nur leichte Registrierungen erfolgen.
     * 
     * LARAVEL BEST PRACTICE:
     * - Config sollte hier geladen werden (mergeConfigFrom)
     * - Commands können hier registriert werden
     */
    public function register(): void
    {
        /**
         * Config laden
         * 
         * mergeConfigFrom lädt die Config aus dem Package-Verzeichnis
         * und merged sie mit der Config aus config/ (falls vorhanden).
         * 
         * WICHTIG: Muss in register() sein, nicht in boot()!
         */
        $this->mergeConfigFrom(__DIR__.'/../config/management-book.php', 'management-book');
        
        /**
         * Commands registrieren (optional)
         * 
         * Falls dein Modul Artisan Commands hat:
         * 
         * if ($this->app->runningInConsole()) {
         *     $this->commands([
         *         \Platform\ManagementBook\Console\Commands\YourCommand::class,
         *     ]);
         * }
         */
    }

    /**
     * Boot Services
     * 
     * Wird NACH register() aufgerufen.
     * Hier erfolgt die eigentliche Modul-Registrierung.
     * 
     * REIHENFOLGE IST WICHTIG:
     * 1. Config prüfen (bereits in register() geladen)
     * 2. Modul bei PlatformCore registrieren
     * 3. Routes laden (nur wenn Modul registriert)
     * 4. Migrationen, Views, Livewire registrieren
     */
    public function boot(): void
    {
        /**
         * SCHRITT 1: Modul-Registrierung prüfen
         * 
         * Prüft ob:
         * - Config vorhanden ist
         * - modules-Tabelle existiert (für Datenbank-Registrierung)
         * 
         * Nur wenn beide Bedingungen erfüllt, wird das Modul registriert.
         */
        if (
            config()->has('management-book.routing') &&
            config()->has('management-book.navigation') &&
            PlatformCore::modulesTableExists()
        ) {
            /**
             * Modul bei PlatformCore registrieren
             * 
             * Dies registriert das Modul in:
             * - Der Modul-Registry (für Navigation, Sidebar)
             * - Der Datenbank (modules-Tabelle)
             * 
             * Die Config wird automatisch aus config/management-book.php geladen.
             */
            PlatformCore::registerModule([
                'key'        => 'management-book', // Eindeutiger Schlüssel
                'title'      => 'Management Book', // Anzeige-Name
                'group'      => 'dev',
                'routing'    => config('management-book.routing'),
                'guard'      => config('management-book.guard'),
                'navigation' => config('management-book.navigation'),
                'sidebar'    => config('management-book.sidebar'),
            ]);
        }

        /**
         * SCHRITT 2: Routes laden
         * 
         * Routes werden nur geladen, wenn das Modul erfolgreich registriert wurde.
         * 
         * ModuleRouter::group() erstellt automatisch:
         * - Route-Prefix (aus Config)
         * - Middleware (web, auth, etc.)
         * - Domain-Handling (für Subdomain-Modus)
         */
        if (PlatformCore::getModule('management-book')) {
            /**
             * Web-Routes (authentifiziert)
             * 
             * Standard: requireAuth = true
             * Für öffentliche Routes: requireAuth = false
             */
            ModuleRouter::group('management-book', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
            
            /**
             * API-Routes (optional)
             * 
             * Falls dein Modul API-Endpoints hat:
             * 
             * ModuleRouter::apiGroup('management-book', function () {
             *     $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
             * });
             */
        }

        /**
         * SCHRITT 3: Migrationen laden
         * 
         * Lädt alle Migrationen aus database/migrations/
         * Wird automatisch bei `php artisan migrate` ausgeführt.
         */
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        /**
         * SCHRITT 4: Config veröffentlichen
         * 
         * Ermöglicht es, die Config in config/management-book.php zu überschreiben.
         * 
         * Publizieren mit:
         * php artisan vendor:publish --tag=config --provider="Platform\ManagementBook\ManagementBookServiceProvider"
         * 
         * WICHTIG: mergeConfigFrom funktioniert auch OHNE Publizierung!
         */
        $this->publishes([
            __DIR__.'/../config/management-book.php' => config_path('management-book.php'),
        ], 'config');

        /**
         * SCHRITT 5: Views laden
         * 
         * Registriert Views unter dem Namespace 'management-book'
         * 
         * Verwendung in Views:
         * @return view('management-book::livewire.dashboard')
         */
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'management-book');
        
        /**
         * SCHRITT 6: Livewire Components registrieren
         * 
         * Registriert alle Livewire-Komponenten automatisch.
         * 
         * Pattern:
         * - Datei: src/Livewire/Dashboard.php
         * - Alias: management-book.dashboard
         * 
         * Verwendung:
         * <livewire:management-book.dashboard />
         */
        $this->registerLivewireComponents();

        // Morph-Alias, Policy, Organization-Bridge (EntityLinkProvider + Link-Hook), Tools
        $this->registerMorphMap();
        $this->registerPolicies();
        $this->registerEntityLinkProvider();
        $this->registerTools();

        /**
         * SCHRITT 7: Tools registrieren (optional)
         * 
         * Falls dein Modul AI/Chat-Tools hat:
         * 
         * $this->registerTools();
         */
    }

    /**
     * Morph-Alias 'management_book' (wie 'project' im Planner) — Voraussetzung für DimensionLinks,
     * HasEntityLinks::getMorphClass() und die Auflösung in der Organization.
     */
    protected function registerMorphMap(): void
    {
        Relation::morphMap([
            \Platform\ManagementBook\Models\ManagementBook::MORPH_ALIAS => \Platform\ManagementBook\Models\ManagementBook::class,
        ]);
    }

    protected function registerPolicies(): void
    {
        Gate::policy(
            \Platform\ManagementBook\Models\ManagementBook::class,
            \Platform\ManagementBook\Policies\ManagementBookPolicy::class
        );
        Gate::policy(
            \Platform\ManagementBook\Models\ManagementBookCommittee::class,
            \Platform\ManagementBook\Policies\ManagementBookCommitteePolicy::class
        );
        Gate::policy(
            \Platform\ManagementBook\Models\ManagementBookMeeting::class,
            \Platform\ManagementBook\Policies\ManagementBookMeetingPolicy::class
        );
        // Sitzungs-Kinder (#1052) erben die Rechte der Sitzung (ManagementBookMeetingChildPolicy)
        foreach (['AgendaItem', 'Document', 'Minutes', 'Decision', 'MeetingAttendee'] as $name) {
            Gate::policy(
                'Platform\\ManagementBook\\Models\\ManagementBook' . $name,
                'Platform\\ManagementBook\\Policies\\ManagementBook' . $name . 'Policy'
            );
        }
        // Strategie-Entitäten erben die Rechte des Management Books
        foreach (['StrategicDocument', 'Forecast', 'FocusArea', 'VisionImage', 'Obstacle', 'Milestone', 'MilestoneContribution'] as $name) {
            Gate::policy(
                'Platform\\ManagementBook\\Models\\ManagementBook' . $name,
                'Platform\\ManagementBook\\Policies\\ManagementBook' . $name . 'Policy'
            );
        }
    }

    /**
     * EntityLinkProvider registrieren (lose Kopplung mit dem Organization-Modul, wie PlannerServiceProvider)
     * und die Aktivierungs-Regel an externe Verknüpfungen koppeln.
     */
    protected function registerEntityLinkProvider(): void
    {
        try {
            resolve(\Platform\Organization\Services\EntityLinkRegistry::class)
                ->register(new \Platform\ManagementBook\Organization\ManagementBookEntityLinkProvider());
        } catch (\Throwable $e) {
            // Organization-Modul nicht geladen
        }

        // Verknüpfungen entstehen nicht nur über den ManagementBookService, sondern auch extern
        // (organization.entity.link.POST, Terminal-App "Verknüpfungen"). Hier greifen dieselben Regeln:
        // wird ein AKTIVES Book an einen Knoten gehängt, löst es das dortige aktive Book ab.
        if (class_exists(\Platform\Organization\Models\OrganizationDimensionLink::class)) {
            \Platform\Organization\Models\OrganizationDimensionLink::created(function ($link) {
                $this->onDimensionLinkChanged($link, \Platform\ManagementBook\Events\ManagementBookLinkChanged::LINKED, true);
            });
            \Platform\Organization\Models\OrganizationDimensionLink::deleted(function ($link) {
                $this->onDimensionLinkChanged($link, \Platform\ManagementBook\Events\ManagementBookLinkChanged::UNLINKED, false);
            });
        }
    }

    protected function onDimensionLinkChanged($link, string $action, bool $enforce): void
    {
        if ($link->linkable_type !== \Platform\ManagementBook\Models\ManagementBook::MORPH_ALIAS) {
            return;
        }

        try {
            $book = \Platform\ManagementBook\Models\ManagementBook::withTrashed()->find($link->linkable_id);
            if (! $book) {
                return;
            }
            \Platform\Organization\Services\EntityDimensionBridge::flush();
            if ($enforce) {
                resolve(\Platform\ManagementBook\Services\ManagementBookService::class)->enforceSingleActive($book);
            }
            \Platform\ManagementBook\Events\ManagementBookLinkChanged::dispatch($book, $action);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ManagementBook: DimensionLink-Hook fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Tools für AI/Chat registrieren (Namen nach Plattform-Konvention: <modul>.<ressource-plural>.<VERB>,
     * z. B. planner.projects.GET). Auto-Discovery gibt es zusätzlich, die explizite Registrierung folgt dem Planner.
     */
    protected function registerTools(): void
    {
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);
            $registry->register(new \Platform\ManagementBook\Tools\ListManagementBooksTool());
            $registry->register(new \Platform\ManagementBook\Tools\CreateManagementBookTool());
            $registry->register(new \Platform\ManagementBook\Tools\UpdateManagementBookTool());
            $registry->register(new \Platform\ManagementBook\Tools\DeleteManagementBookTool());
            $registry->register(new \Platform\ManagementBook\Tools\ListCommitteesTool());
            $registry->register(new \Platform\ManagementBook\Tools\CreateCommitteeTool());
            $registry->register(new \Platform\ManagementBook\Tools\UpdateCommitteeTool());
            $registry->register(new \Platform\ManagementBook\Tools\DeleteCommitteeTool());
            $registry->register(new \Platform\ManagementBook\Tools\ListMeetingsTool());
            $registry->register(new \Platform\ManagementBook\Tools\CreateMeetingTool());
            $registry->register(new \Platform\ManagementBook\Tools\UpdateMeetingTool());
            $registry->register(new \Platform\ManagementBook\Tools\DeleteMeetingTool());
            $registry->register(new \Platform\ManagementBook\Tools\ListAgendaItemsTool());
            $registry->register(new \Platform\ManagementBook\Tools\CreateAgendaItemTool());
            $registry->register(new \Platform\ManagementBook\Tools\UpdateAgendaItemTool());
            $registry->register(new \Platform\ManagementBook\Tools\DeleteAgendaItemTool());
            $registry->register(new \Platform\ManagementBook\Tools\ListDocumentsTool());
            $registry->register(new \Platform\ManagementBook\Tools\CreateDocumentTool());
            $registry->register(new \Platform\ManagementBook\Tools\UpdateDocumentTool());
            $registry->register(new \Platform\ManagementBook\Tools\DeleteDocumentTool());
            $registry->register(new \Platform\ManagementBook\Tools\ListMinutesTool());
            $registry->register(new \Platform\ManagementBook\Tools\CreateMinutesTool());
            $registry->register(new \Platform\ManagementBook\Tools\UpdateMinutesTool());
            $registry->register(new \Platform\ManagementBook\Tools\DeleteMinutesTool());
            $registry->register(new \Platform\ManagementBook\Tools\ListDecisionsTool());
            $registry->register(new \Platform\ManagementBook\Tools\CreateDecisionTool());
            $registry->register(new \Platform\ManagementBook\Tools\UpdateDecisionTool());
            $registry->register(new \Platform\ManagementBook\Tools\DeleteDecisionTool());
            $registry->register(new \Platform\ManagementBook\Tools\ListMeetingAttendeesTool());
            $registry->register(new \Platform\ManagementBook\Tools\CreateMeetingAttendeeTool());
            $registry->register(new \Platform\ManagementBook\Tools\UpdateMeetingAttendeeTool());
            $registry->register(new \Platform\ManagementBook\Tools\DeleteMeetingAttendeeTool());
            $registry->register(new \Platform\ManagementBook\Tools\GetBookStrategyTool());
            $registry->register(new \Platform\ManagementBook\Tools\UpdateBookStrategyTool());
            $registry->register(new \Platform\ManagementBook\Tools\ListForecastsTool());
            $registry->register(new \Platform\ManagementBook\Tools\CreateForecastTool());
            $registry->register(new \Platform\ManagementBook\Tools\UpdateForecastTool());
            $registry->register(new \Platform\ManagementBook\Tools\DeleteForecastTool());
            $registry->register(new \Platform\ManagementBook\Tools\CreateForecastVersionTool());
            $registry->register(new \Platform\ManagementBook\Tools\ListFocusAreasTool());
            $registry->register(new \Platform\ManagementBook\Tools\CreateFocusAreaTool());
            $registry->register(new \Platform\ManagementBook\Tools\UpdateFocusAreaTool());
            $registry->register(new \Platform\ManagementBook\Tools\DeleteFocusAreaTool());
            $registry->register(new \Platform\ManagementBook\Tools\ListVisionImagesTool());
            $registry->register(new \Platform\ManagementBook\Tools\CreateVisionImageTool());
            $registry->register(new \Platform\ManagementBook\Tools\UpdateVisionImageTool());
            $registry->register(new \Platform\ManagementBook\Tools\DeleteVisionImageTool());
            $registry->register(new \Platform\ManagementBook\Tools\ListObstaclesTool());
            $registry->register(new \Platform\ManagementBook\Tools\CreateObstacleTool());
            $registry->register(new \Platform\ManagementBook\Tools\UpdateObstacleTool());
            $registry->register(new \Platform\ManagementBook\Tools\DeleteObstacleTool());
            $registry->register(new \Platform\ManagementBook\Tools\ListMilestonesTool());
            $registry->register(new \Platform\ManagementBook\Tools\CreateMilestoneTool());
            $registry->register(new \Platform\ManagementBook\Tools\UpdateMilestoneTool());
            $registry->register(new \Platform\ManagementBook\Tools\DeleteMilestoneTool());
            $registry->register(new \Platform\ManagementBook\Tools\GetTransformationMapTool());
            $registry->register(new \Platform\ManagementBook\Tools\UpdateTransformationMapTool());
            $registry->register(new \Platform\ManagementBook\Tools\ListMilestoneContributionsTool());
            $registry->register(new \Platform\ManagementBook\Tools\CreateMilestoneContributionTool());
            $registry->register(new \Platform\ManagementBook\Tools\DeleteMilestoneContributionTool());
        } catch (\Throwable $e) {
            // ToolRegistry nicht verfügbar
        }
    }

    /**
     * Registriert alle Livewire-Komponenten automatisch
     * 
     * Scant das src/Livewire/ Verzeichnis rekursiv und registriert
     * alle PHP-Dateien als Livewire-Komponenten.
     * 
     * NAMING CONVENTION:
     * - Datei: src/Livewire/Dashboard.php
     * - Namespace: Platform\ManagementBook\Livewire\Dashboard
     * - Alias: management-book.dashboard
     * 
     * - Datei: src/Livewire/Entity/Index.php
     * - Namespace: Platform\ManagementBook\Livewire\Entity\Index
     * - Alias: management-book.entity.index
     * 
     * @return void
     */
    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\ManagementBook\\Livewire';
        $prefix = 'management-book';

        // Prüfe ob Verzeichnis existiert
        if (!is_dir($basePath)) {
            return;
        }

        // Rekursiv alle PHP-Dateien durchsuchen
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            // Nur PHP-Dateien verarbeiten
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            // Relativen Pfad extrahieren
            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            
            // Klassenpfad generieren (z.B. Entity\Index -> Entity\Index)
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            // Prüfe ob Klasse existiert
            if (!class_exists($class)) {
                continue;
            }

            // Alias generieren (z.B. Entity\Index -> entity.index)
            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            // Livewire-Komponente registrieren
            Livewire::component($alias, $class);
        }
    }
}
