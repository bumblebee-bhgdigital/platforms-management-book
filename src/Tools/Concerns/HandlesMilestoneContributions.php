<?php

namespace Platform\ManagementBook\Tools\Concerns;

use Platform\Core\Models\Team;
use Platform\ManagementBook\Models\ManagementBookMilestone;
use Platform\ManagementBook\Models\ManagementBookMilestoneContribution;

/** Gemeinsame Helfer der management-book.milestone_contributions.*-Tools. */
trait HandlesMilestoneContributions
{
    use HandlesStrategy;

    protected function findMilestone(int $id, Team $team): ?ManagementBookMilestone
    {
        return ManagementBookMilestone::query()->where('team_id', $team->id)->find($id);
    }

    /** @return array<string,mixed> */
    protected function contributionToArray(ManagementBookMilestoneContribution $c): array
    {
        return array_merge($c->toAlignmentArray(), [
            'uuid' => $c->uuid,
            'linkable_type' => $c->linkable_type,
            'linkable_id' => $c->linkable_id,
            'linkable_title' => $c->linkable?->title ?? $c->linkable?->name,
            'created_by_user_id' => $c->created_by_user_id,
            'created_at' => $c->created_at?->toIso8601String(),
        ]);
    }
}
