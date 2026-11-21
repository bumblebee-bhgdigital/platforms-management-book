<?php

namespace Tests\Feature;

require_once __DIR__ . '/UiTestHarness.php';

use Platform\Core\Contracts\ToolContext;
use Tests\TestCase;

/**
 * Smoke-Test für die Strategie-Quelle der Organisation (Management Book als einzige Quelle).
 * Braucht eine Verifikations-App mit dem Organization-Stand, der Strategy\StrategySources anbietet;
 * ohne diese Klasse wird der Test übersprungen. Aufruf: tests/run-in-taiste.sh tests/Feature/StrategySourceSmokeTest.php
 */
class StrategySourceSmokeTest extends TestCase
{
    use UiTestHarness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootModule();
        if (! class_exists(\Platform\Organization\Strategy\StrategySources::class)) {
            $this->markTestSkipped('Organization ohne StrategySources (alter Stand).');
        }
        foreach (glob(base_path('vendor/martin3r/platform-activity-log/database/migrations/*.php')) as $f) {
            try {
                (require $f)->up();
            } catch (\Throwable $e) {
            }
        }
        // Provider registriert die Quelle beim Booten; im Harness wurde er nachträglich registriert.
        \Platform\Organization\Strategy\StrategySources::register(new \Platform\ManagementBook\Organization\ManagementBookStrategySource());
    }

    public function test_source_brief_and_agent_paths(): void
    {
        $d = $this->seedBook();
        $S = \Platform\Organization\Strategy\StrategySources::class;

        $group = null;
        if (\Illuminate\Support\Facades\Schema::hasTable('organization_entity_type_groups')) {
            $cols = \Illuminate\Support\Facades\Schema::getColumnListing('organization_entity_type_groups');
            $row = array_intersect_key(['name' => 'Gruppe', 'code' => 'grp', 'sort_order' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()], array_flip($cols));
            $group = \Illuminate\Support\Facades\DB::table('organization_entity_type_groups')->insertGetId($row);
        }
        $type = \Platform\Organization\Models\OrganizationEntityType::create(array_filter([
            'code' => 'venture', 'name' => 'Venture', 'vsm_class' => 'carrier', 'is_active' => true, 'entity_type_group_id' => $group,
        ], fn ($v) => $v !== null));
        $entity = \Platform\Organization\Models\OrganizationEntity::create([
            'name' => 'BHG.TEST', 'team_id' => $d['teamId'], 'user_id' => $d['user']->id, 'is_active' => true, 'entity_type_id' => $type->id,
        ]);

        // Ohne verknüpftes, aktives Book: keine Strategie
        $this->assertNull($S::forEntity($entity));

        $service = app(\Platform\ManagementBook\Services\ManagementBookService::class);
        $service->link($d['book'], $entity->id);
        $service->activate($d['book']->fresh());

        $s = $S::forEntity($entity);
        $this->assertIsArray($s);
        $this->assertSame('Wir machen Gastfreundschaft verlässlich.', $s['mission']['content']);
        $this->assertCount(2, $s['focus_areas']);
        $this->assertSame('Eventgeschäft', $s['focus_areas'][0]['title']);
        $this->assertSame(4, $s['milestone_total']);
        $this->assertContains('reached', array_column($s['focus_areas'][0]['milestones'], 'status'));
        $this->assertCount(1, $s['forecasts']);
        $this->assertSame($d['book']->id, $s['source']['id']);
        $this->assertNotEmpty($s['transformation_map']['years']);

        // Agenten-API: Meilensteine im Slot (nächstes Jahr Q2 = "Plattform live")
        $year = (int) now()->year + 1;
        $ms = $S::milestonesForTeam($d['teamId'], [[$year, 2]]);
        $this->assertCount(1, $ms);
        $this->assertSame('Plattform live', $ms[0]['title']);
        $this->assertSame($entity->id, $ms[0]['entity_id']);
        $this->assertSame('Digitalisierung', $ms[0]['focus_area']);

        // Ausrichtung: Objekt zahlt ein (idempotent), dann Alignments lesen.
        // Im Harness gibt es keine OKR-Tabellen → ein vorhandenes Objekt mit Morph-Alias (das Book selbst) als Stellvertreter.
        $A = \Platform\ManagementBook\Models\ManagementBook::MORPH_ALIAS;
        $bid = $d['book']->id;
        $r = $S::contribute($d['teamId'], $ms[0]['id'], $A, $bid, 50, $d['user']->id);
        $this->assertTrue($r['created']);
        $this->assertSame('Plattform live', $r['alignment']['milestone']);
        $this->assertSame($entity->id, $r['alignment']['entity_id']);
        $r2 = $S::contribute($d['teamId'], $ms[0]['id'], $A, $bid, null, $d['user']->id);
        $this->assertFalse($r2['created']);
        $this->assertNull($S::contribute($d['teamId'], 999999, $A, $bid, null, null), 'unbekannter Meilenstein');
        $this->assertNull($S::contribute($d['teamId'] + 99, $ms[0]['id'], $A, $bid, null, null), 'fremdes Team');
        try {
            $S::contribute($d['teamId'], $ms[0]['id'], $A, 999999, null, null);
            $this->fail('nicht existierendes Objekt muss abgelehnt werden');
        } catch (\InvalidArgumentException $e) {
            $this->assertNotSame('', $e->getMessage());
        }
        $al = $S::alignmentsFor([$A => [$bid, 999999]]);
        $this->assertCount(1, $al[$A][$bid]);
        $this->assertSame(50, $al[$A][$bid][0]['weight']);
        $this->assertSame($entity->id, $al[$A][$bid][0]['entity_id']);

        // Brief: Strategie-Abschnitt kommt aus dem Book (Abschnitt direkt prüfen – Leserechte über den Org-Graphen
        // bildet das Harness nicht ab). Nächster Knoten mit Strategie in der Linie, auch ohne Carrier-Typ.
        $brief = new \Platform\Organization\Tools\GetBriefTool();
        $m = new \ReflectionMethod($brief, 'strategy');
        $m->setAccessible(true);
        $part = $m->invoke($brief, [$entity]);
        $this->assertSame('BHG.TEST', $part['carrier']);
        $this->assertSame('management-book', $part['source']);
        $this->assertSame($d['book']->id, $part['management_book_id']);
        $this->assertSame(['Eventgeschäft', 'Digitalisierung'], $part['focus_areas']);
        $this->assertStringContainsString('Gastfreundschaft', $part['mission']);
        $this->assertStringContainsString('management-book.strategy.GET', file_get_contents((new \ReflectionClass($brief))->getFileName()));

        // Die alten Strategie-Tools gibt es nicht mehr
        $this->assertFalse(class_exists('Platform\\Organization\\Tools\\GetEntityStrategyTool', false));
    }
}
