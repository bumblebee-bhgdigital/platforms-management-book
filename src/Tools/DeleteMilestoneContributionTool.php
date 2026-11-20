<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Models\ManagementBookMilestoneContribution;
use Platform\ManagementBook\Tools\Concerns\HandlesMilestoneContributions;

class DeleteMilestoneContributionTool implements ToolContract, ToolMetadataContract
{
    use HandlesMilestoneContributions;

    public function getName(): string
    {
        return 'management-book.milestone_contributions.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /management-book/milestone-contributions - Löst einen Beitrag eines Objectives/Key Results von einem Meilenstein (hartes Löschen der Verknüpfung; das Objective/Key Result selbst bleibt unberührt). '
            . 'Entweder id ODER milestone_id + linkable_type + linkable_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'Contribution-ID.'],
                'milestone_id' => ['type' => 'integer', 'description' => 'Alternative zu id: Meilenstein.'],
                'linkable_type' => ['type' => 'string', 'description' => 'Alternative zu id: Objekttyp.'],
                'linkable_id' => ['type' => 'integer', 'description' => 'Alternative zu id: Objekt-ID.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
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
            $query = ManagementBookMilestoneContribution::query()->where('team_id', $team->id);
            if (! empty($arguments['id'])) {
                $query->whereKey((int) $arguments['id']);
            } elseif (! empty($arguments['milestone_id']) && ! empty($arguments['linkable_type']) && ! empty($arguments['linkable_id'])) {
                $alias = ManagementBookMilestoneContribution::resolveType((string) $arguments['linkable_type']);
                if (! $alias) {
                    return ToolResult::error('VALIDATION_ERROR', "Objekttyp '{$arguments['linkable_type']}' ist nicht registriert.");
                }
                $query->where('milestone_id', (int) $arguments['milestone_id'])
                    ->where('linkable_type', $alias)
                    ->where('linkable_id', (int) $arguments['linkable_id']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'id oder milestone_id + linkable_type + linkable_id angeben.');
            }
            $contribution = $query->first();
            if (! $contribution) {
                return ToolResult::error('NOT_FOUND', 'Beitrag nicht gefunden.');
            }
            if ($denied = $this->authorizeModel($context, 'delete', $contribution, 'Beitrag')) {
                return $denied;
            }

            $id = $contribution->id;
            $contribution->delete();

            return ToolResult::success(['id' => $id, 'message' => 'Beitrag gelöst.']);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Lösen des Beitrags: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('milestone_contributions', 'DELETE');
    }
}
