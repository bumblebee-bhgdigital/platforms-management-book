<?php

namespace Tests\Feature;

require_once __DIR__ . '/UiTestHarness.php';

use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Organization\ManagementBookEntityLinkProvider;
use Tests\TestCase;

/**
 * #1060: Das Book meldet Kennzahlen über die Dimension Bridge an Organisations-Knoten.
 * Aufruf: tests/run-in-taiste.sh tests/Feature/OrganizationMetricsTest.php
 */
class OrganizationMetricsTest extends TestCase
{
    use UiTestHarness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootModule();
    }

    public function test_metrics_for_seeded_book(): void
    {
        $s = $this->seedBook();
        $provider = new ManagementBookEntityLinkProvider();

        $m = $provider->metrics(ManagementBook::MORPH_ALIAS, [10 => [$s['book']->id]])[10];

        $this->assertSame(1, $m['mb_books']);
        $this->assertSame(0, $m['mb_reviews_due']);
        // 4 offene Risiken (geschlossenes zählt nicht): 12 hoch, 20 kritisch, 4 niedrig, 1 unbewertet
        $this->assertSame(4, $m['mb_risks_open']);
        $this->assertSame(2, $m['mb_risks_hot']);
        $this->assertSame(1, $m['mb_risks_critical']);
        $this->assertSame(1, $m['mb_risks_unrated']);
        $this->assertSame(20, $m['mb_risk_score_max']);
        $this->assertSame(2, $m['mb_focus_areas']);
        // Meilensteine: 1 erreicht, 1 überfällig (Vorjahr Q4, in Arbeit), 1 künftig, 1 ohne Termin
        $this->assertSame(4, $m['mb_milestones_total']);
        $this->assertSame(1, $m['mb_milestones_reached']);
        $this->assertSame(1, $m['mb_milestones_overdue']);
        $this->assertSame(25, $m['mb_milestones_reached_percent']);
        // Beschlüsse: einer offen und überfällig, einer umgesetzt
        $this->assertSame(1, $m['mb_decisions_open']);
        $this->assertSame(1, $m['mb_decisions_overdue']);
        // Sitzungen: vor 3 Wochen gehalten, nächste in einer Woche
        $this->assertSame(1, $m['mb_meetings_held_30d']);
        $this->assertSame(7, $m['mb_days_to_next_meeting']);
        // Handlungsfähigkeit: ein geführter Punkt mit 5 von 6 Kriterien
        $this->assertSame(83, $m['mb_meeting_capability_percent']);

        foreach ($m as $key => $value) {
            $this->assertIsInt($value, $key);
        }
    }

    public function test_two_books_on_one_node_and_inactive_books(): void
    {
        $s = $this->seedBook();
        $service = app(\Platform\ManagementBook\Services\ManagementBookService::class);
        $empty = $service->create($s['user'], $s['team'], ['title' => 'Leeres Book']);
        $archived = $service->create($s['user'], $s['team'], ['title' => 'Altes Book']);
        $archived->forceFill(['status' => 'archived'])->save();
        $archived->risks()->create(['team_id' => $s['teamId'], 'title' => 'Alt', 'probability' => 5, 'impact' => 5]);

        $provider = new ManagementBookEntityLinkProvider();
        $result = $provider->metrics(ManagementBook::MORPH_ALIAS, [
            1 => [$s['book']->id, $empty->id, $archived->id],
            2 => [$archived->id],
            3 => [999999],
        ]);

        $this->assertSame(2, $result[1]['mb_books']);
        $this->assertSame(4, $result[1]['mb_risks_open'], 'archiviertes Book zählt nicht');
        $this->assertSame(20, $result[1]['mb_risk_score_max']);
        $this->assertArrayNotHasKey(2, $result, 'nur archivierte Books -> keine Kennzahlen');
        $this->assertArrayNotHasKey(3, $result, 'unbekannte IDs -> keine Kennzahlen');

        $emptyOnly = $provider->metrics(ManagementBook::MORPH_ALIAS, [4 => [$empty->id]])[4];
        $this->assertSame(0, $emptyOnly['mb_risks_open']);
        $this->assertArrayNotHasKey('mb_risk_score_max', $emptyOnly);
        $this->assertArrayNotHasKey('mb_milestones_reached_percent', $emptyOnly);
        $this->assertArrayNotHasKey('mb_days_to_next_meeting', $emptyOnly);

        $this->assertSame([], $provider->metrics(ManagementBook::MORPH_ALIAS, []));
        $this->assertSame([], $provider->metrics('project', [1 => [$s['book']->id]]));
    }

    public function test_definitions_are_valid_in_organization_registry(): void
    {
        $s = $this->seedBook();
        $provider = new ManagementBookEntityLinkProvider();
        $defs = $provider->metricDefinitions();

        $keys = array_keys($provider->metrics(ManagementBook::MORPH_ALIAS, [1 => [$s['book']->id]])[1]);
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $defs, "Definition für {$key} fehlt");
        }
        foreach ($defs as $key => $def) {
            foreach (['label', 'group', 'direction', 'unit'] as $field) {
                $this->assertArrayHasKey($field, $def, "{$key}.{$field}");
            }
            $this->assertEmpty($def['is_dimension_primary'] ?? false, "{$key} darf nicht primary sein");
        }

        if (! class_exists(\Platform\Organization\Services\EntityLinkRegistry::class)) {
            $this->markTestIncomplete('Organization nicht geladen');
        }
        $registry = resolve(\Platform\Organization\Services\EntityLinkRegistry::class);
        $registry->register($provider);
        $all = $registry->allMetricDefinitions(); // wirft bei ungültigem subset_of/primary
        $this->assertSame('Offene Risiken', $all['mb_risks_open']['label']);
        $this->assertArrayHasKey('management', $registry->allMetricGroups());

        $batch = $registry->computeMetricsBatch([5 => [ManagementBook::MORPH_ALIAS => [$s['book']->id]]]);
        $this->assertSame(4, $batch[5]['mb_risks_open']);
    }
}
