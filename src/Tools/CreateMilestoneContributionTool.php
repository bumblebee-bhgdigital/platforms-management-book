<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Models\ManagementBookMilestoneContribution;
use Platform\ManagementBook\Tools\Concerns\HandlesMilestoneContributions;

class CreateMilestoneContributionTool implements ToolContract, ToolMetadataContract
{
    use HandlesMilestoneContributions;

    public function getName(): string
    {
        return 'management-book.milestone_contributions.POST';
    }

    public function getDescription(): string
    {
        return 'POST /management-book/milestone-contributions - Ein Objekt (typisch Objective oder Key Result aus dem OKR-Modul) zahlt auf einen Meilenstein '
            . 'der Transformation Map eines Management Books ein (Ausrichtung Umsetzung → Strategie). Pflicht: milestone_id, linkable_type (Morph-Alias z. B. okr_objective / okr_key_result, Kurzname oder Klasse), linkable_id. '
            . 'Optional: weight (1-100). Idempotent: existiert der Beitrag, wird nur weight aktualisiert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'milestone_id' => ['type' => 'integer', 'description' => 'Meilenstein-ID des Management Books (ERFORDERLICH).'],
                'linkable_type' => ['type' => 'string', 'description' => 'Objekttyp (ERFORDERLICH), z. B. okr_objective oder okr_key_result.'],
                'linkable_id' => ['type' => 'integer', 'description' => 'ID des Objekts (ERFORDERLICH).'],
                'weight' => ['type' => 'integer', 'description' => 'Optional 1-100: Anteil am Meilenstein (für spätere Fortschritts-Hochrechnung).'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ],
            'required' => ['milestone_id', 'linkable_type', 'linkable_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            $milestoneId = (int) ($arguments['milestone_id'] ?? 0);
            $type = (string) ($arguments['linkable_type'] ?? '');
            $linkableId = (int) ($arguments['linkable_id'] ?? 0);
            if ($milestoneId <= 0 || $linkableId <= 0 || $type === '') {
                return ToolResult::error('VALIDATION_ERROR', 'milestone_id, linkable_type und linkable_id sind erforderlich.');
            }
            $milestone = $this->findMilestone($milestoneId, $team);
            if (! $milestone) {
                return ToolResult::error('NOT_FOUND', "Meilenstein #{$milestoneId} nicht gefunden.");
            }
            if ($denied = $this->authorizeModel($context, 'update', $milestone, 'Meilenstein')) {
                return $denied;
            }

            // Typ vorab normalisieren, damit Kurzname/Alias/Klasse auf denselben Datensatz treffen (Idempotenz).
            $alias = ManagementBookMilestoneContribution::resolveType($type);
            if (! $alias) {
                return ToolResult::error('VALIDATION_ERROR', "Objekttyp '{$type}' ist nicht registriert.");
            }
            $contribution = ManagementBookMilestoneContribution::firstOrNew([
                'milestone_id' => $milestone->id,
                'linkable_type' => $alias,
                'linkable_id' => $linkableId,
            ]);
            $created = ! $contribution->exists;
            if (array_key_exists('weight', $arguments)) {
                $contribution->weight = $arguments['weight'] !== null ? (int) $arguments['weight'] : null;
            }
            if ($created) {
                $contribution->team_id = $milestone->team_id;
                $contribution->created_by_user_id = $context->user->id;
            }
            $contribution->save();
            $contribution->load('milestone.focusArea', 'linkable');

            return ToolResult::success([
                'contribution' => $this->contributionToArray($contribution),
                'created' => $created,
                'message' => $created ? 'Beitrag zum Meilenstein angelegt.' : 'Beitrag existierte bereits — aktualisiert.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return ToolResult::error('VALIDATION_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen des Beitrags: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        $meta = $this->toolMetadata('milestone_contributions', 'POST');
        $meta['idempotent'] = true;
        $meta['tags'][] = 'okr';

        return $meta;
    }
}
