<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Enums\MilestoneStatus;
use Platform\ManagementBook\Models\ManagementBookMilestone;
use Platform\ManagementBook\Tools\Concerns\HandlesTransformationMap;

class GetTransformationMapTool implements ToolContract, ToolMetadataContract
{
    use HandlesTransformationMap;

    public function getName(): string
    {
        return 'management-book.transformation_map.GET';
    }

    public function getDescription(): string
    {
        return 'GET /management-book/books/{id}/transformation-map - Transformation Map eines Management Books: alle Meilensteine als Waypoints über die Zeit '
            . '(sortiert nach Jahr, Quartal, Reihenfolge) mit Status, Fokusraum-Bahn, Beiträgen aus dem OKR-Modul (Objectives/Key Results) und Kennzahlen '
            . '(je Status, erreicht in %, nächster offener Waypoint, überfällige Waypoints). '
            . 'Argumente: management_book_id (pflicht), status, focus_area_id, from_year, to_year, include_contributions (default true). '
            . 'Ändern: management-book.milestones.PUT bzw. gebündelt management-book.transformation_map.PUT.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'management_book_id' => ['type' => 'integer', 'description' => 'PFLICHT. ID des Management Books.'],
                'status' => ['type' => 'string', 'enum' => MilestoneStatus::values(), 'description' => 'Optional: nur Waypoints mit diesem Status.'],
                'focus_area_id' => ['type' => 'integer', 'description' => 'Optional: nur Waypoints dieses Fokusraums.'],
                'from_year' => ['type' => 'integer', 'description' => 'Optional: Waypoints ab diesem Zieljahr (undatierte bleiben enthalten).'],
                'to_year' => ['type' => 'integer', 'description' => 'Optional: Waypoints bis einschließlich diesem Zieljahr (undatierte bleiben enthalten).'],
                'include_contributions' => ['type' => 'boolean', 'description' => 'Optional (default true): Beiträge (Objectives/Key Results) je Waypoint mitliefern; sonst nur contributions_count.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ],
            'required' => ['management_book_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            ['book' => $book, 'error' => $error] = $this->findBook($arguments, $team, $context, 'view');
            if ($error) {
                return $error;
            }
            if (! empty($arguments['status']) && ! MilestoneStatus::tryFrom((string) $arguments['status'])) {
                return ToolResult::error('VALIDATION_ERROR', 'status muss einer von ' . implode(', ', MilestoneStatus::values()) . ' sein.');
            }
            $withContributions = array_key_exists('include_contributions', $arguments) ? (bool) $arguments['include_contributions'] : true;

            $query = ManagementBookMilestone::query()->where('management_book_id', $book->id)
                ->with(['focusArea', 'contributions.linkable'])
                ->whereHas('focusArea');
            if (! empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }
            if (! empty($arguments['focus_area_id'])) {
                $query->where('focus_area_id', (int) $arguments['focus_area_id']);
            }
            if (! empty($arguments['from_year'])) {
                $from = (int) $arguments['from_year'];
                $query->where(fn ($q) => $q->whereNull('target_year')->orWhere('target_year', '>=', $from));
            }
            if (! empty($arguments['to_year'])) {
                $to = (int) $arguments['to_year'];
                $query->where(fn ($q) => $q->whereNull('target_year')->orWhere('target_year', '<=', $to));
            }
            $milestones = $query->get()->sort(function (ManagementBookMilestone $a, ManagementBookMilestone $b) {
                // Undatierte ans Ende; sonst Jahr, Quartal, Datum, Reihenfolge, ID
                return [$a->target_year === null ? 1 : 0, $a->target_year, $a->target_quarter ?? 5, $a->target_date?->toDateString() ?? '9999', $a->order, $a->id]
                    <=> [$b->target_year === null ? 1 : 0, $b->target_year, $b->target_quarter ?? 5, $b->target_date?->toDateString() ?? '9999', $b->order, $b->id];
            })->values();

            $now = now();
            $byStatus = array_fill_keys(MilestoneStatus::values(), 0);
            $overdue = [];
            $next = null;
            $timeline = [];
            $undated = [];
            $lanes = [];

            $waypoints = $milestones->map(function (ManagementBookMilestone $m, int $i) use (&$byStatus, &$overdue, &$next, &$timeline, &$undated, &$lanes, $withContributions, $now) {
                $byStatus[$m->status->value]++;
                $due = $this->waypointDueDate($m);
                $isOverdue = $this->isOpenWaypoint($m) && $due !== null && $due->lt($now);
                if ($isOverdue) {
                    $overdue[] = $m->id;
                }
                if ($next === null && $this->isOpenWaypoint($m) && ! $isOverdue) {
                    $next = $m->id;
                }
                if ($m->target_year) {
                    $timeline[$m->target_year . '-' . ($m->target_quarter ?? 0)][] = $m->id;
                } else {
                    $undated[] = $m->id;
                }
                $lanes[$m->focus_area_id][] = $m->id;

                $data = array_merge($this->itemToArray($m), [
                    'sequence' => $i + 1,
                    'focus_area' => $m->focusArea?->title,
                    'overdue' => $isOverdue,
                    'contributions_count' => $m->contributions->count(),
                ]);
                if ($withContributions) {
                    $data['contributions'] = $m->contributions->map(fn ($c) => [
                        'contribution_id' => $c->id,
                        'linkable_type' => $c->linkable_type,
                        'linkable_id' => $c->linkable_id,
                        'linkable_title' => $c->linkable?->title ?? $c->linkable?->name,
                        'weight' => $c->weight,
                    ])->values()->all();
                }

                return $data;
            })->values()->all();

            ksort($timeline);
            $timelineOut = [];
            foreach ($timeline as $key => $ids) {
                [$year, $quarter] = explode('-', $key);
                $timelineOut[] = ['year' => (int) $year, 'quarter' => (int) $quarter ?: null, 'waypoint_ids' => $ids];
            }

            $total = count($waypoints);
            $countable = $total - $byStatus[MilestoneStatus::CANCELLED->value];

            return ToolResult::success([
                'book' => ['id' => $book->id, 'title' => $book->title, 'horizon_year' => $book->horizon_year],
                'waypoints' => $waypoints,
                'timeline' => $timelineOut,
                'undated_waypoint_ids' => $undated,
                'focus_area_lanes' => $book->focusAreas()->get()->map(fn ($fa) => [
                    'id' => $fa->id,
                    'title' => $fa->title,
                    'order' => (int) $fa->order,
                    'waypoint_ids' => $lanes[$fa->id] ?? [],
                ])->all(),
                'summary' => [
                    'total' => $total,
                    'by_status' => $byStatus,
                    'reached_percent' => $countable > 0 ? (int) round($byStatus[MilestoneStatus::REACHED->value] / $countable * 100) : null,
                    'next_waypoint_id' => $next,
                    'overdue_waypoint_ids' => $overdue,
                    'contributions' => array_sum(array_column($waypoints, 'contributions_count')),
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Transformation Map: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('transformation_map', 'GET');
    }
}
