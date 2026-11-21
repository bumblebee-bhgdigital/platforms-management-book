<?php

namespace Platform\ManagementBook\Services;

use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\Organization\Services\EntityDimensionBridge;

/**
 * Verortung eines Gremiums an einem Organisations-Knoten (z. B. „BHG.DIGITAL Lenkungsausschuss“) –
 * lose per DimensionLink wie beim Book (Alias management_book_committee). Ein Gremium hängt an höchstens
 * einem Knoten; Verknüpfen ersetzt eine bestehende Verortung. Ohne Organisations-Modul: no-op.
 */
class CommitteeNodeService
{
    public function available(): bool
    {
        return class_exists(EntityDimensionBridge::class);
    }

    /** @return int[] */
    public function nodeIds(ManagementBookCommittee $committee): array
    {
        if (! $this->available()) {
            return [];
        }

        return EntityDimensionBridge::linksForLinkables([ManagementBookCommittee::MORPH_ALIAS], [$committee->id], false)
            ->pluck('entity_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** Gremium an $entityId hängen (ersetzt eine bestehende Verortung). */
    public function place(ManagementBookCommittee $committee, int $entityId): void
    {
        if (! $this->available()) {
            return;
        }
        if ($this->nodeIds($committee) === []) {
            EntityDimensionBridge::createLink($entityId, ManagementBookCommittee::MORPH_ALIAS, $committee->id, ['team_id' => $committee->team_id]);
        } else {
            EntityDimensionBridge::replaceLinks(ManagementBookCommittee::MORPH_ALIAS, $committee->id, $entityId, ['team_id' => $committee->team_id]);
        }
        EntityDimensionBridge::flush();
    }

    /** Verortung lösen (alle Knoten). */
    public function unplace(ManagementBookCommittee $committee): void
    {
        if (! $this->available()) {
            return;
        }
        foreach ($this->nodeIds($committee) as $entityId) {
            EntityDimensionBridge::deleteLink($entityId, ManagementBookCommittee::MORPH_ALIAS, $committee->id);
        }
        EntityDimensionBridge::flush();
    }
}
