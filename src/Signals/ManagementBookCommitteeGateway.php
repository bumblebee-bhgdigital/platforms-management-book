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
        $byCarrier = $this->committeesByCarrier();
        if ($byCarrier === []) {
            return null;
        }
        $proxy = null;
        $current = OrganizationEntity::with('type:id,code,vsm_class')->find($unitEntityId);
        for ($depth = 0; $current && $depth < 30; $depth++) {
            if (isset($byCarrier[$current->id])) {
                return $this->describe($byCarrier[$current->id], (int) $current->id) + ['proxy_for_entity_id' => $proxy];
            }
            // Träger ohne eigenen aktiven LA: der LA darüber arbeitet stellvertretend für den obersten solchen Träger
            if ($current->type?->vsm_class === 'carrier') {
                $proxy = (int) $current->id;
            }
            $current = $current->parent_entity_id ? OrganizationEntity::with('type:id,code,vsm_class')->find($current->parent_entity_id) : null;
        }

        return null;
    }

    public function committeeInfo(int $committeeEntityId): ?array
    {
        $c = ManagementBookCommittee::query()->where('organization_entity_id', $committeeEntityId)->first();
        $carrier = $c ? $this->carrierOf($committeeEntityId) : null;

        return $c ? $this->describe($c, $carrier) : null;
    }

    public function committeeAboveCommittee(int $committeeEntityId): ?array
    {
        $carrier = $this->carrierOf($committeeEntityId);
        $parent = $carrier ? OrganizationEntity::whereKey($carrier)->value('parent_entity_id') : null;

        return $parent ? $this->committeeAbove((int) $parent) : null;
    }

    /**
     * Stewards des Regelkreises: Beziehung „betreut (Regelkreis)“ (Code stewards) von Person/Agent zum Gremium-Knoten;
     * zusätzlich der verantwortliche Agent des Gremiums (Übergang).
     */
    public function stewards(int $committeeEntityId): array
    {
        $out = [];
        if (class_exists(\Platform\Organization\Models\OrganizationEntityRelationship::class)) {
            $rels = \Platform\Organization\Models\OrganizationEntityRelationship::query()
                ->where('to_entity_id', $committeeEntityId)
                ->whereHas('relationType', fn ($q) => $q->where('code', 'stewards'))
                ->with('fromEntity.type:id,code')->get();
            foreach ($rels as $r) {
                if ($e = $r->fromEntity) {
                    $out[(int) $e->id] = ['entity_id' => (int) $e->id, 'name' => $e->name, 'user_id' => $e->linked_user_id ? (int) $e->linked_user_id : null, 'is_agent' => $e->type?->code === 'agent'];
                }
            }
        }
        $c = ManagementBookCommittee::query()->where('organization_entity_id', $committeeEntityId)->first();
        if ($c && $c->responsible_agent_id) {
            $e = OrganizationEntity::query()->where('linked_user_id', $c->responsible_agent_id)->first();
            $key = $e ? (int) $e->id : 'u' . $c->responsible_agent_id;
            $out[$key] ??= ['entity_id' => $e ? (int) $e->id : null, 'name' => $e?->name ?? \Platform\Core\Models\User::whereKey($c->responsible_agent_id)->value('name'), 'user_id' => (int) $c->responsible_agent_id, 'is_agent' => true];
        }

        return array_values($out);
    }

    public function committeesStewardedBy(int $userId): array
    {
        $out = [];
        foreach (ManagementBookCommittee::query()->where('type', CommitteeType::STEERING_COMMITTEE->value)->where('status', 'active')->whereNotNull('organization_entity_id')->get() as $c) {
            $isSteward = collect($this->stewards((int) $c->organization_entity_id))->contains(fn ($s) => (int) ($s['user_id'] ?? 0) === $userId);
            if ($isSteward) {
                $out[] = $this->describe($c, $this->carrierOf((int) $c->organization_entity_id));
            }
        }

        return $out;
    }

    /** @return array<int, ManagementBookCommittee> Träger-Einheit => aktiver LA */
    private function committeesByCarrier(): array
    {
        $byCarrier = [];
        foreach (ManagementBookCommittee::query()->where('type', CommitteeType::STEERING_COMMITTEE->value)->where('status', 'active')->whereNotNull('organization_entity_id')->get() as $c) {
            if ($carrier = $this->carrierOf((int) $c->organization_entity_id)) {
                $byCarrier[$carrier] ??= $c;
            }
        }

        return $byCarrier;
    }

    /** Ebene aus dem Trägertyp: Gruppe (Venture-Träger, Holding, Gruppe) oder operative Einheit. */
    private function describe(ManagementBookCommittee $c, ?int $carrierId): array
    {
        $carrierType = $carrierId ? OrganizationEntity::with('type:id,code')->find($carrierId)?->type?->code : null;

        return [
            'committee_id' => (int) $c->id,
            'entity_id' => (int) $c->organization_entity_id,
            'name' => $c->organizationNode?->name ?? $c->name,
            'carrier_entity_id' => $carrierId,
            'carrier_name' => $carrierId ? OrganizationEntity::whereKey($carrierId)->value('name') : null,
            'level' => in_array($carrierType, ['venture_builder', 'holding', 'group'], true) ? 'group' : 'unit',
        ];
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
