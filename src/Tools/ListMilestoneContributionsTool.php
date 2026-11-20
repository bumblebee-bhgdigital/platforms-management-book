<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Models\ManagementBookMilestoneContribution;
use Platform\ManagementBook\Tools\Concerns\HandlesMilestoneContributions;

class ListMilestoneContributionsTool implements ToolContract, ToolMetadataContract
{
    use HandlesMilestoneContributions;

    public function getName(): string
    {
        return 'management-book.milestone_contributions.GET';
    }

    public function getDescription(): string
    {
        return 'GET /management-book/milestone-contributions - Listet Beiträge von Objectives/Key Results (OKR-Modul) auf Meilensteine der Management Books (nur sichtbare Books). '
            . 'Filter: milestone_id (was zahlt auf M ein?), linkable_type + linkable_id (worauf zahlt dieses Objective/Key Result ein?), management_book_id (alle Beiträge auf die Map eines Books), limit/offset.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'milestone_id' => ['type' => 'integer', 'description' => 'Optional: nur Beiträge auf diesen Meilenstein.'],
                'linkable_type' => ['type' => 'string', 'description' => 'Optional (mit linkable_id): Objekttyp (Kurzname, Morph-Alias z. B. okr_objective / okr_key_result, oder Klasse).'],
                'linkable_id' => ['type' => 'integer', 'description' => 'Optional (mit linkable_type).'],
                'management_book_id' => ['type' => 'integer', 'description' => 'Optional: nur Beiträge auf Meilensteine dieses Books.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
                'limit' => ['type' => 'integer', 'description' => 'Optional: max. Anzahl (Default 100, max 500).'],
                'offset' => ['type' => 'integer', 'description' => 'Optional: Offset.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }

            $query = ManagementBookMilestoneContribution::query()->where('team_id', $team->id)
                ->whereIn('management_book_id', $this->visibleBookIds($team, $context))
                ->with(['milestone.focusArea', 'linkable']);
            if (! empty($arguments['milestone_id'])) {
                $query->where('milestone_id', (int) $arguments['milestone_id']);
            }
            if (! empty($arguments['management_book_id'])) {
                $query->where('management_book_id', (int) $arguments['management_book_id']);
            }
            if (! empty($arguments['linkable_type']) && ! empty($arguments['linkable_id'])) {
                $alias = ManagementBookMilestoneContribution::resolveType((string) $arguments['linkable_type']);
                if (! $alias) {
                    return ToolResult::error('VALIDATION_ERROR', "Objekttyp '{$arguments['linkable_type']}' ist nicht registriert.");
                }
                $query->where('linkable_type', $alias)->where('linkable_id', (int) $arguments['linkable_id']);
            }

            $total = (clone $query)->count();
            [$limit, $offset] = $this->pageArgs($arguments, 100, 500);
            $rows = $query->orderBy('milestone_id')->orderBy('id')->skip($offset)->take($limit)->get();

            return ToolResult::success([
                'contributions' => $rows->map(fn ($c) => $this->contributionToArray($c))->all(),
                'total' => $total,
                'team_id' => $team->id,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Beiträge: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('milestone_contributions', 'GET');
    }
}
