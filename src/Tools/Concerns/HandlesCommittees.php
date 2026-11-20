<?php

namespace Platform\ManagementBook\Tools\Concerns;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Enums\CommitteeMemberRole;
use Platform\ManagementBook\Enums\CommitteeStatus;
use Platform\ManagementBook\Enums\CommitteeType;
use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\ManagementBook\Models\ManagementBookCommitteeMember;

/** Gemeinsame Helfer der management-book.committees.*-Tools (Serialisierung, Rechte, Mitglieder). */
trait HandlesCommittees
{
    use HandlesManagementBooks;

    protected function authorizeCommittee(ToolContext $context, string $ability, ManagementBookCommittee $committee): ?ToolResult
    {
        return Gate::forUser($context->user)->allows($ability, $committee)
            ? null
            : ToolResult::error('ACCESS_DENIED', "Keine Berechtigung ({$ability}) für Gremium #{$committee->id}.");
    }

    /** @return array<string,mixed> */
    protected function committeeToArray(ManagementBookCommittee $c): array
    {
        $c->loadMissing('members');

        return [
            'id' => $c->id,
            'uuid' => $c->uuid,
            'team_id' => $c->team_id,
            'management_book_id' => $c->management_book_id,
            'name' => $c->name,
            'type' => $c->type?->value,
            'type_label' => $c->type?->label(),
            'description' => $c->description,
            'cadence' => $c->cadence,
            'chair_user_id' => $c->chair_user_id,
            'responsible_agent_id' => $c->responsible_agent_id,
            'signal_set_id' => $c->signal_set_id,
            'status' => $c->status?->value,
            'status_label' => $c->status?->label(),
            'members' => $c->members->map(fn (ManagementBookCommitteeMember $m) => [
                'user_id' => $m->user_id,
                'agent_id' => $m->agent_id,
                'role' => $m->role?->value,
                'role_label' => $m->role?->label(),
            ])->values()->all(),
            'created_by' => $c->created_by,
            'created_at' => $c->created_at?->toIso8601String(),
            'updated_at' => $c->updated_at?->toIso8601String(),
        ];
    }

    /** Gemeinsame Property-Definitionen für POST/PUT. */
    protected function committeeFieldSchema(): array
    {
        return [
            'name' => ['type' => 'string', 'description' => 'Name des Gremiums (frei benennbar).'],
            'type' => ['type' => 'string', 'enum' => CommitteeType::values(), 'description' => 'Typ: steering_committee (Lenkungsausschuss), strategy_meeting (Strategie-Meeting), board (Board), other (Sonstiges).'],
            'description' => ['type' => 'string', 'description' => 'Beschreibung / Zweck des Gremiums.'],
            'cadence' => ['type' => 'string', 'description' => 'Rhythmus als Freitext/Info, z. B. "monatlich". Keine Ablauflogik.'],
            'chair_user_id' => ['type' => 'integer', 'description' => 'Vorsitz (User-ID).'],
            'responsible_agent_id' => ['type' => 'integer', 'description' => 'Verantwortlicher Agent (User-ID eines Agent-Users).'],
            'signal_set_id' => ['type' => 'integer', 'description' => 'Optional: Verweis auf ein Signal-Set im Signals-Modul.'],
            'status' => ['type' => 'string', 'enum' => CommitteeStatus::values(), 'description' => 'Status: active, dormant (ruhend), dissolved (aufgelöst).'],
            'members' => [
                'type' => 'array',
                'description' => 'Mitglieder; ersetzt beim Ändern die gesamte Liste. Je Eintrag genau eines von user_id/agent_id plus role.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'user_id' => ['type' => 'integer'],
                        'agent_id' => ['type' => 'integer'],
                        'role' => ['type' => 'string', 'enum' => CommitteeMemberRole::values(), 'description' => 'chair (Vorsitz), member (Mitglied), guest (Gast), secretary (Protokoll). Default member.'],
                    ],
                ],
            ],
        ];
    }

    /** Prüft type/status/members; liefert eine Fehlermeldung oder null. */
    protected function validateCommitteeInput(array $arguments, int $teamId): ?string
    {
        if (isset($arguments['type']) && ! CommitteeType::tryFrom((string) $arguments['type'])) {
            return 'type muss einer von ' . implode(', ', CommitteeType::values()) . ' sein.';
        }
        if (isset($arguments['status']) && ! CommitteeStatus::tryFrom((string) $arguments['status'])) {
            return 'status muss einer von ' . implode(', ', CommitteeStatus::values()) . ' sein.';
        }

        $userIds = array_filter([$arguments['chair_user_id'] ?? null, $arguments['responsible_agent_id'] ?? null]);
        foreach ($arguments['members'] ?? [] as $i => $m) {
            if (! is_array($m)) {
                return "members[{$i}] muss ein Objekt sein.";
            }
            if (empty($m['user_id']) === empty($m['agent_id'])) {
                return "members[{$i}]: genau eines von user_id oder agent_id angeben.";
            }
            if (isset($m['role']) && ! CommitteeMemberRole::tryFrom((string) $m['role'])) {
                return "members[{$i}].role muss einer von " . implode(', ', CommitteeMemberRole::values()) . ' sein.';
            }
            $userIds[] = $m['user_id'] ?? $m['agent_id'];
        }

        $userIds = array_unique(array_map('intval', $userIds));
        if ($userIds !== []) {
            $known = \Platform\Core\Models\User::whereIn('id', $userIds)->pluck('id')->all();
            $missing = array_diff($userIds, $known);
            if ($missing !== []) {
                return 'Unbekannte User-ID(s): ' . implode(', ', $missing) . '.';
            }
        }

        return null;
    }

    /** Ersetzt die Mitgliederliste des Gremiums. */
    protected function syncMembers(ManagementBookCommittee $committee, array $members): void
    {
        $committee->members()->delete();
        $seen = [];
        foreach ($members as $m) {
            $key = ! empty($m['user_id']) ? 'u' . (int) $m['user_id'] : 'a' . (int) $m['agent_id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $committee->members()->create([
                'user_id' => ! empty($m['user_id']) ? (int) $m['user_id'] : null,
                'agent_id' => ! empty($m['agent_id']) ? (int) $m['agent_id'] : null,
                'role' => $m['role'] ?? CommitteeMemberRole::MEMBER->value,
            ]);
        }
        $committee->unsetRelation('members');
    }
}
