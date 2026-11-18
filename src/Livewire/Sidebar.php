<?php

/**
 * Modul-Sidebar: Management Books nach Knoten (Organisations-Entity) gruppiert,
 * nicht verknüpfte Books separat. Aufbau 1:1 wie die Planner-Sidebar (Entity-Baum
 * über EntityDimensionBridge + EntityAncestorService), zusätzlich mit Verknüpfen/Umhängen/Lösen.
 */

namespace Platform\ManagementBook\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Platform\ManagementBook\Livewire\Concerns\LinksBooksToNodes;
use Platform\ManagementBook\Livewire\Concerns\ResolvesTeamFamily;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\EntityAncestorService;
use Platform\Organization\Services\EntityDimensionBridge;

class Sidebar extends Component
{
    use ResolvesTeamFamily, LinksBooksToNodes;

    /** Sidebar neu rendern, wenn irgendwo ein Book verknüpft/gelöst/angelegt wurde. */
    #[On('updateSidebar')]
    public function updateSidebar(): void
    {
    }

    public function render()
    {
        $user = auth()->user();
        $teamId = $user?->currentTeam?->id;

        $empty = [
            'entityTypeGroups' => collect(),
            'unlinkedBooks' => collect(),
            'linkCandidates' => [],
            'linkedBookIds' => [],
        ];

        if (! $user || ! $teamId) {
            return view('management-book::livewire.sidebar', $empty);
        }

        // Archivierte Books bleiben als Historie über Übersicht/Detail erreichbar, nicht in der Navigation.
        $books = ManagementBook::query()
            ->whereIn('team_id', $this->relevantTeamIds($user))
            ->visibleTo($user)
            ->where('status', '!=', \Platform\ManagementBook\Enums\ManagementBookStatus::ARCHIVED->value)
            ->orderBy('title')
            ->get();

        // Knoten-Verknüpfungen (DimensionLink, Alias 'management_book')
        $entityBookMap = []; // entity_id => [book_id]
        $linkedBookIds = [];
        $links = EntityDimensionBridge::linksForLinkables([ManagementBook::MORPH_ALIAS], $books->pluck('id')->all());
        foreach ($links as $link) {
            if (! $link->entity_id) {
                continue;
            }
            $entityBookMap[$link->entity_id][] = $link->linkable_id;
            $linkedBookIds[] = $link->linkable_id;
        }
        foreach ($entityBookMap as $entityId => $ids) {
            $entityBookMap[$entityId] = array_values(array_unique($ids));
        }
        $linkedBookIds = array_values(array_unique($linkedBookIds));

        // Aufwärts-Traversierung: Tree-Parents (nur struktureller Baum, kein engagement_with-Channel)
        $ancestorService = new EntityAncestorService();
        $channels = [];
        foreach ($ancestorService->expandEntitiesWithAncestors(array_keys($entityBookMap), $channels) as $entityId) {
            $entityBookMap[$entityId] ??= [];
        }

        $entityTypeGroups = collect();
        if ($entityBookMap !== []) {
            $entities = OrganizationEntity::with('type')->whereIn('id', array_keys($entityBookMap))->get()->keyBy('id');
            $hierarchy = $ancestorService->buildParentChildrenMap($entities, $channels);
            $childrenMap = $hierarchy['parent_to_children'];

            $buildTree = function (int $entityId) use (&$buildTree, $entities, $childrenMap, $entityBookMap, $books): ?array {
                $entity = $entities->get($entityId);
                if (! $entity) {
                    return null;
                }

                $childNodes = collect($childrenMap[$entityId] ?? [])->map(fn ($id) => $buildTree($id))->filter();
                $childrenByType = $childNodes
                    ->groupBy(fn ($c) => $c['type_id'])
                    ->map(function ($group) use ($entities) {
                        $first = $group->first();
                        $type = $entities->get($first['entity_id'])?->type;

                        return [
                            'type_id' => $first['type_id'],
                            'type_name' => $type?->name ?? 'Sonstige',
                            'type_icon' => $type?->icon ?? null,
                            'sort_order' => $type?->sort_order ?? 999,
                            'children' => $group->sortBy('entity_name')->values(),
                        ];
                    })
                    ->sortBy('sort_order')->values();

                $nodeBooks = collect($entityBookMap[$entityId] ?? [])
                    ->map(fn ($id) => $books->firstWhere('id', $id))->filter()
                    // aktives Book zuerst, dann Entwurf, abgelöst
                    ->sortBy(fn ($b) => [['active' => 0, 'draft' => 1, 'superseded' => 2][$b->status->value] ?? 3, $b->title])
                    ->values();

                $total = $nodeBooks->count() + $childNodes->sum('total_books');
                if ($total === 0) {
                    return null;
                }

                return [
                    'entity_id' => $entityId,
                    'entity_name' => $entity->name,
                    'type_id' => $entity->type?->id,
                    'books' => $nodeBooks,
                    'children_by_type' => $childrenByType,
                    'total_books' => $total,
                ];
            };

            $grouped = [];
            foreach ($hierarchy['roots'] as $entityId) {
                $entity = $entities->get($entityId);
                if (! $entity || ! $entity->type || ! ($tree = $buildTree($entityId))) {
                    continue;
                }
                $typeId = $entity->type->id;
                $grouped[$typeId] ??= [
                    'type_id' => $typeId,
                    'type_name' => $entity->type->name,
                    'type_icon' => $entity->type->icon,
                    'sort_order' => $entity->type->sort_order ?? 999,
                    'entities' => [],
                ];
                $grouped[$typeId]['entities'][] = $tree;
            }

            $entityTypeGroups = collect($grouped)->sortBy('sort_order')
                ->map(function ($g) {
                    $g['entities'] = collect($g['entities'])->sortBy('entity_name')->values();

                    return $g;
                })->values();
        }

        return view('management-book::livewire.sidebar', [
            'entityTypeGroups' => $entityTypeGroups,
            'unlinkedBooks' => $books->reject(fn ($b) => in_array($b->id, $linkedBookIds, true))->values(),
            'linkCandidates' => $this->linkCandidates(),
            'linkedBookIds' => $linkedBookIds,
        ]);
    }
}
