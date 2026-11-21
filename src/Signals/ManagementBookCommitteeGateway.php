<?php

namespace Platform\ManagementBook\Signals;

use Platform\ManagementBook\Enums\CommitteeType;
use Platform\ManagementBook\Models\ManagementBookAgendaItem;
use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\ManagementBook\Models\ManagementBookDecision;
use Platform\ManagementBook\Models\ManagementBookMeeting;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Signals\Contracts\CommitteeGateway;

/**
 * Gremien-Brücke für das Signals-Modul (Regelkreis Einheit → Lenkungsausschuss).
 * Der LA „über“ einer Einheit ist der Lenkungsausschuss, dessen Träger-Einheit (nächster Vorfahr des Gremium-Knotens,
 * der kein Ordner/Gremium ist) die Einheit selbst oder ihr nächster Vorfahr ist.
 */
class ManagementBookCommitteeGateway implements CommitteeGateway
{
    private const NOT_A_CARRIER = ['container', 'board'];

    public function committeeAbove(int $unitEntityId): ?array
    {
        $committees = ManagementBookCommittee::query()
            ->where('type', CommitteeType::STEERING_COMMITTEE->value)
            ->where('status', 'active')
            ->whereNotNull('organization_entity_id')
            ->get();
        if ($committees->isEmpty()) {
            return null;
        }
        $byCarrier = [];
        foreach ($committees as $c) {
            if ($carrier = $this->carrierOf((int) $c->organization_entity_id)) {
                $byCarrier[$carrier] ??= $c;
            }
        }
        $current = OrganizationEntity::find($unitEntityId);
        for ($depth = 0; $current && $depth < 30; $depth++) {
            if (isset($byCarrier[$current->id])) {
                $c = $byCarrier[$current->id];

                return ['committee_id' => (int) $c->id, 'entity_id' => (int) $c->organization_entity_id, 'name' => $c->organizationNode?->name ?? $c->name];
            }
            $current = $current->parent_entity_id ? OrganizationEntity::find($current->parent_entity_id) : null;
        }

        return null;
    }

    public function nextMeeting(int $committeeEntityId): ?array
    {
        $committee = ManagementBookCommittee::query()->where('organization_entity_id', $committeeEntityId)->first();
        if (! $committee) {
            return null;
        }
        $meeting = ManagementBookMeeting::query()->where('committee_id', $committee->id)
            ->where('scheduled_at', '>=', now()->startOfDay())
            ->whereNotIn('status', ['held', 'cancelled'])
            ->orderBy('scheduled_at')->first();

        return $meeting ? [
            'meeting_id' => (int) $meeting->id,
            'title' => $meeting->title,
            'scheduled_at' => $meeting->scheduled_at?->toIso8601String(),
            'url' => $this->meetingUrl($meeting),
        ] : null;
    }

    public function frames(int $unitEntityId): array
    {
        return ManagementBookDecision::query()
            ->where('kind', 'frame')
            ->where('status', '!=', 'discarded')
            ->whereHas('agendaItem', fn ($q) => $q->where('organization_entity_id', $unitEntityId))
            ->orderByDesc('created_at')->limit(20)->get()
            ->map(fn (ManagementBookDecision $d) => [
                'decision_id' => (int) $d->id,
                'text' => $d->text,
                'since' => $d->created_at?->toDateString(),
                'meeting_id' => (int) $d->meeting_id,
            ])->all();
    }

    public function draftAgendaItem(int $meetingId, array $item, string $linkType, int $linkId): array
    {
        $meeting = ManagementBookMeeting::findOrFail($meetingId);
        $existing = ManagementBookAgendaItem::query()->where('meeting_id', $meetingId)
            ->where('linked_object_type', $linkType)->where('linked_object_id', $linkId)->first();
        if ($existing) {
            return ['type' => 'management_book_agenda_item', 'id' => (int) $existing->id, 'meeting_id' => $meetingId, 'url' => $this->meetingUrl($meeting)];
        }

        $agenda = $meeting->agendaItems()->create([
            'team_id' => $meeting->team_id,
            'position' => (int) $meeting->agendaItems()->max('position') + 1,
            'title' => mb_substr($item['title'], 0, 255),
            'kind' => 'regulation',
            'goal' => $item['goal'],
            'observation' => $item['observation'],
            'obstacle' => $item['obstacle'],
            'local_solution' => $item['local_solution'],
            'system_support' => $item['system_support'],
            'local_solution_confirmed' => (bool) ($item['local_solution_confirmed'] ?? false),
            'organization_entity_id' => $item['unit_entity_id'] ?? null,
            'owner_agent_id' => $item['agent_user_id'] ?? null,
            'linked_object_type' => $linkType,
            'linked_object_id' => $linkId,
            'proposal_status' => ManagementBookAgendaItem::PROPOSAL_DRAFT,
        ]);

        return ['type' => 'management_book_agenda_item', 'id' => (int) $agenda->id, 'meeting_id' => $meetingId, 'url' => $this->meetingUrl($meeting)];
    }

    /** Träger-Einheit eines Gremium-Knotens: nächster Vorfahr, der weder Ordner noch Gremium ist. */
    private function carrierOf(int $committeeEntityId): ?int
    {
        $current = OrganizationEntity::with('type:id,code')->find($committeeEntityId);
        for ($depth = 0; $current && $depth < 30; $depth++) {
            $current = $current->parent_entity_id ? OrganizationEntity::with('type:id,code')->find($current->parent_entity_id) : null;
            if ($current && ! in_array($current->type?->code, self::NOT_A_CARRIER, true)) {
                return (int) $current->id;
            }
        }

        return null;
    }

    private function meetingUrl(ManagementBookMeeting $meeting): ?string
    {
        try {
            return route('management-book.meetings.show', $meeting);
        } catch (\Throwable) {
            return null;
        }
    }
}
