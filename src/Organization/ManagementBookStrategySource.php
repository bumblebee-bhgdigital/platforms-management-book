<?php

namespace Platform\ManagementBook\Organization;

use Illuminate\Support\Facades\Route;
use Platform\ManagementBook\Enums\StrategicDocumentType;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Models\ManagementBookFocusArea;
use Platform\ManagementBook\Models\ManagementBookForecast;
use Platform\ManagementBook\Models\ManagementBookMilestone;
use Platform\ManagementBook\Models\ManagementBookMilestoneContribution;
use Platform\ManagementBook\Models\ManagementBookStrategicDocument;
use Platform\ManagementBook\Services\ManagementBookService;
use Platform\Organization\Contracts\StrategySource;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\EntityDimensionBridge;

/**
 * Strategie-Quelle der Organisation: Das aktive Management Book eines Knotens IST dessen Strategie.
 * Registriert im ManagementBookServiceProvider, wenn das Organization-Modul die Registry anbietet.
 * Brief, Portfolio-API, Agenten-API (Meilensteine, Ausrichtung) und Entity-Seite lesen hierüber.
 */
class ManagementBookStrategySource implements StrategySource
{
    public function forEntity(OrganizationEntity $entity): ?array
    {
        $book = app(ManagementBookService::class)->findActiveForNode((int) $entity->id);
        if (! $book) {
            return null;
        }

        $doc = function (StrategicDocumentType $type) use ($book) {
            $d = ManagementBookStrategicDocument::activeFor($book->id, $type);

            return $d ? [
                'title' => $d->title,
                'content' => $d->content,
                'version' => $d->version,
                'valid_from' => $d->valid_from?->toDateString(),
            ] : null;
        };
        $mission = $doc(StrategicDocumentType::MISSION);
        $vision = $doc(StrategicDocumentType::VISION);

        $focusAreaModels = ManagementBookFocusArea::query()->where('management_book_id', $book->id)
            ->with([
                'visionImages' => fn ($q) => $q->orderBy('order')->orderBy('id'),
                'obstacles' => fn ($q) => $q->orderBy('order')->orderBy('id'),
                'milestones' => fn ($q) => $q->orderBy('target_year')->orderBy('target_quarter')->orderBy('order')->orderBy('id'),
            ])
            ->orderBy('order')->orderBy('id')->get();

        $milestoneTotal = 0;
        $years = [];
        $grid = [];
        $noYear = [];
        $item = fn ($i) => ['id' => $i->id, 'title' => $i->title, 'description' => $i->description, 'central_question' => $i->central_question];

        $focusAreas = $focusAreaModels->map(function (ManagementBookFocusArea $fa) use (&$milestoneTotal, &$years, &$grid, &$noYear, $item) {
            $milestones = $fa->milestones->map(fn (ManagementBookMilestone $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'description' => $m->description,
                'central_question' => $m->central_question,
                'target_year' => $m->target_year !== null ? (int) $m->target_year : null,
                'target_quarter' => $m->target_quarter !== null ? (int) $m->target_quarter : null,
                'order' => (int) $m->order,
                'status' => $m->status?->value,
            ])->values()->all();

            foreach ($milestones as $m) {
                if ($m['target_year']) {
                    $years[$m['target_year']] = true;
                    $grid[$fa->id][$m['target_year']][] = $m;
                } else {
                    $noYear[$fa->id][] = $m;
                }
            }
            $milestoneTotal += count($milestones);

            return [
                'id' => $fa->id,
                'title' => $fa->title,
                'description' => $fa->description,
                'order' => (int) $fa->order,
                'central_question_vision_images' => $fa->central_question_vision_images,
                'central_question_obstacles' => $fa->central_question_obstacles,
                'central_question_milestones' => $fa->central_question_milestones,
                'vision_images' => $fa->visionImages->map($item)->values()->all(),
                'obstacles' => $fa->obstacles->map($item)->values()->all(),
                'milestones' => $milestones,
            ];
        })->values()->all();
        ksort($years);

        $forecasts = ManagementBookForecast::query()->where('management_book_id', $book->id)
            ->with('currentVersion')->orderBy('target_date')->orderBy('id')->get()
            ->map(fn (ManagementBookForecast $f) => [
                'id' => $f->id,
                'title' => $f->title,
                'target_date' => $f->target_date?->toDateString(),
                'content' => $f->currentVersion?->content ?? $f->content,
                'current_version' => $f->currentVersion?->version,
            ])->values()->all();

        if (! $mission && ! $vision && $focusAreas === [] && $forecasts === []) {
            return null;
        }

        return [
            'mission' => $mission,
            'vision' => $vision,
            'focus_areas' => $focusAreas,
            'transformation_map' => ['years' => array_keys($years), 'grid' => $grid, 'no_year' => $noYear],
            'forecasts' => $forecasts,
            'milestone_total' => $milestoneTotal,
            'has_any' => true,
            'source' => $this->sourceMeta($book),
        ];
    }

    public function milestonesForTeam(int $teamId, array $slots): array
    {
        if ($slots === []) {
            return [];
        }

        $rows = ManagementBookMilestone::query()
            ->where('team_id', $teamId)
            ->whereHas('book', fn ($q) => $q->whereNull('deleted_at'))
            ->where(function ($q) use ($slots) {
                foreach ($slots as [$y, $qu]) {
                    $q->orWhere(fn ($w) => $w->where('target_year', (int) $y)->where('target_quarter', (int) $qu));
                }
            })
            ->with(['focusArea:id,title'])
            ->orderBy('target_year')->orderBy('target_quarter')->orderBy('management_book_id')->orderBy('order')
            ->get();

        $nodes = $this->nodesForBooks($rows->pluck('management_book_id')->unique()->values()->all());

        return $rows->map(fn (ManagementBookMilestone $m) => [
            'id' => $m->id,
            'title' => $m->title,
            'central_question' => $m->central_question,
            'entity_id' => $nodes[$m->management_book_id]['id'] ?? null,
            'entity' => $nodes[$m->management_book_id]['name'] ?? null,
            'focus_area' => $m->focusArea?->title,
            'target_year' => $m->target_year,
            'target_quarter' => $m->target_quarter,
            'status' => $m->status?->value,
            'management_book_id' => $m->management_book_id,
        ])->values()->all();
    }

    public function contribute(int $teamId, int $milestoneId, string $linkableType, int $linkableId, ?int $weight, ?int $userId): ?array
    {
        $milestone = ManagementBookMilestone::query()->where('team_id', $teamId)->find($milestoneId);
        if (! $milestone) {
            return null;
        }

        $alias = ManagementBookMilestoneContribution::resolveType($linkableType) ?? $linkableType;
        $c = ManagementBookMilestoneContribution::firstOrNew([
            'milestone_id' => $milestone->id,
            'linkable_type' => $alias,
            'linkable_id' => $linkableId,
        ]);
        $created = ! $c->exists;
        if ($weight !== null) {
            $c->weight = $weight;
        }
        if ($created) {
            $c->team_id = $milestone->team_id;
            $c->created_by_user_id = $userId;
        }
        $c->save(); // validiert weight, setzt management_book_id (InvalidArgumentException bei Fehlern)
        $c->load('milestone.focusArea');

        return ['created' => $created, 'alignment' => $this->alignment($c)];
    }

    public function alignmentsFor(array $idsByAlias): array
    {
        $idsByAlias = array_filter($idsByAlias);
        if ($idsByAlias === []) {
            return [];
        }

        $rows = ManagementBookMilestoneContribution::query()
            ->where(function ($w) use ($idsByAlias) {
                foreach ($idsByAlias as $alias => $ids) {
                    $w->orWhere(fn ($x) => $x->where('linkable_type', $alias)->whereIn('linkable_id', array_map('intval', $ids)));
                }
            })
            ->with('milestone.focusArea')
            ->get();

        $nodes = $this->nodesForBooks($rows->pluck('management_book_id')->unique()->values()->all());
        $out = [];
        foreach ($rows as $c) {
            $out[$c->linkable_type][(int) $c->linkable_id][] = $this->alignment($c, $nodes);
        }

        return $out;
    }

    /** Ausrichtung im bisherigen Organisations-Format (+ Book-Bezug). */
    private function alignment(ManagementBookMilestoneContribution $c, ?array $nodes = null): array
    {
        $nodes ??= $this->nodesForBooks([(int) $c->management_book_id]);
        $node = $nodes[$c->management_book_id] ?? null;

        return array_merge($c->toAlignmentArray(), [
            'entity_id' => $node['id'] ?? null,
            'entity' => $node['name'] ?? null,
        ]);
    }

    /** Erster Knoten je Book: [book_id => ['id','name']]. */
    private function nodesForBooks(array $bookIds): array
    {
        if ($bookIds === []) {
            return [];
        }
        $links = EntityDimensionBridge::linksForLinkables([ManagementBook::MORPH_ALIAS], $bookIds, false);
        $names = OrganizationEntity::whereIn('id', $links->pluck('entity_id')->filter()->unique()->all())->pluck('name', 'id');
        $out = [];
        foreach ($links as $link) {
            if ($link->entity_id && ! isset($out[$link->linkable_id])) {
                $out[(int) $link->linkable_id] = ['id' => (int) $link->entity_id, 'name' => $names[$link->entity_id] ?? null];
            }
        }

        return $out;
    }

    private function sourceMeta(ManagementBook $book): array
    {
        return [
            'label' => 'Management Book',
            'id' => $book->id,
            'title' => $book->title,
            'status' => $book->status?->value,
            'url' => Route::has('management-book.books.show') ? route('management-book.books.show', $book) : null,
        ];
    }
}
