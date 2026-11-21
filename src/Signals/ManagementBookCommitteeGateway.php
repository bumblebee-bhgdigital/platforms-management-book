<?php

namespace Platform\ManagementBook\Signals;

use Illuminate\Support\Facades\Event;
use Platform\ManagementBook\Enums\CommitteeType;
use Platform\ManagementBook\Events\AgendaItemDecided;
use Platform\ManagementBook\Events\AgendaProposalRejected;
use Platform\ManagementBook\Models\ManagementBookAgendaItem;
use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\ManagementBook\Models\ManagementBookDecision;
use Platform\ManagementBook\Models\ManagementBookMeeting;
use Platform\Signals\Contracts\CommitteeGateway;

/**
 * Gremien-Brücke für das Signals-Modul. Liefert nur, was das Book selbst weiß: aktive Lenkungsausschüsse,
 * Sitzungen, Entscheidungsrahmen, Entwürfe und den Rückweg der Beschlüsse. Regelkreis, Ebenen und Stewards
 * leitet Signals selbst ab – das Book kennt weder Knotentypen der Organisation noch Signale.
 */
class ManagementBookCommitteeGateway implements CommitteeGateway
{
    public function committees(): array
    {
        return ManagementBookCommittee::query()
            ->where('type', CommitteeType::STEERING_COMMITTEE->value)->where('status', 'active')
            ->whereNotNull('organization_entity_id')->orderBy('id')->get()
            ->map(fn (ManagementBookCommittee $c) => [
                'committee_id' => (int) $c->id,
                'entity_id' => (int) $c->organization_entity_id,
                'name' => $c->organizationNode?->name ?? $c->name,
                'responsible_user_ids' => $c->responsible_agent_id ? [(int) $c->responsible_agent_id] : [],
            ])->all();
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

    public function frames(int $entityId): array
    {
        return ManagementBookDecision::query()
            ->where('kind', self::KIND_FRAME)
            ->where('status', '!=', 'discarded')
            ->whereHas('agendaItem', fn ($q) => $q->where('organization_entity_id', $entityId))
            ->orderByDesc('created_at')->limit(20)->get()
            ->map(fn (ManagementBookDecision $d) => [
                'type' => ManagementBookDecision::MORPH_ALIAS,
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
            return ['type' => ManagementBookAgendaItem::MORPH_ALIAS, 'id' => (int) $existing->id, 'meeting_id' => $meetingId, 'url' => $this->meetingUrl($meeting)];
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

        return ['type' => ManagementBookAgendaItem::MORPH_ALIAS, 'id' => (int) $agenda->id, 'meeting_id' => $meetingId, 'url' => $this->meetingUrl($meeting)];
    }

    public function onOutcome(callable $handler): void
    {
        Event::listen(AgendaItemDecided::class, function (AgendaItemDecided $e) use ($handler) {
            $item = $e->agendaItem;
            if ($item->linked_object_type && $item->linked_object_id) {
                $handler((string) $item->linked_object_type, (int) $item->linked_object_id, (string) ($e->decision->kind ?: self::KIND_MEASURE),
                    ManagementBookDecision::MORPH_ALIAS, (int) $e->decision->id, (string) $e->decision->text,
                    $e->decision->created_by ? (int) $e->decision->created_by : null);
            }
        });
        Event::listen(AgendaProposalRejected::class, function (AgendaProposalRejected $e) use ($handler) {
            $item = $e->agendaItem;
            if ($item->linked_object_type && $item->linked_object_id) {
                $handler((string) $item->linked_object_type, (int) $item->linked_object_id, self::KIND_RETURN_TO_UNIT,
                    ManagementBookAgendaItem::MORPH_ALIAS, (int) $item->id, 'Vom Vorsitz nicht auf die Agenda genommen – lokal lösen.', $e->byUserId);
            }
        });
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
