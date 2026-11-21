<?php

namespace Platform\ManagementBook\Tools\Concerns;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Enums\CommitteeMemberRole;
use Platform\ManagementBook\Enums\CommitteeStatus;
use Platform\ManagementBook\Enums\CommitteeType;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\ManagementBook\Services\CommitteeNodeService;

/**
 * Gemeinsame Helfer der management-book.committees.*-Tools.
 * Gremium = Organisations-Knoten: Name, Beschreibung, Mitglieder und Vorsitz liegen am Knoten
 * (Beziehungen is_part_of / manages / is_active_in); Schreiben über die Tools schreibt dorthin.
 */
trait HandlesCommittees
{
    use HandlesManagementBooks;

    protected function nodes(): CommitteeNodeService
    {
        return app(CommitteeNodeService::class);
    }

    protected function authorizeCommittee(ToolContext $context, string $ability, ManagementBookCommittee $committee): ?ToolResult
    {
        return Gate::forUser($context->user)->allows($ability, $committee)
            ? null
            : ToolResult::error('ACCESS_DENIED', "Keine Berechtigung ({$ability}) für Gremium #{$committee->id}.");
    }

    /**
     * @param bool $withFormat volle Format-Beschreibung (Felder, Arten, Kriterien) statt nur Name/Leitfrage
     * @return array<string,mixed>
     */
    protected function committeeToArray(ManagementBookCommittee $c, bool $withFormat = false): array
    {
        $format = $c->format();
        $service = $this->nodes();
        $nodeId = $service->nodeId($c);
        $node = $nodeId ? $service->node($c) : null;
        $source = $node ? 'organization' : 'book';
        $members = $service->members($c);
        $chair = $members->firstWhere('role', 'chair') ?? $service->chair($c);

        return [
            'id' => $c->id,
            'uuid' => $c->uuid,
            'team_id' => $c->team_id,
            'management_book_id' => $c->management_book_id,
            'organization_entity_id' => $nodeId,
            'organization_node' => $node ? [
                'entity_id' => $node->id,
                'name' => $node->name,
                'type' => $node->type?->name,
                'url' => $service->nodeUrl($node->id),
            ] : null,
            'name' => $c->name,
            'description' => $c->description,
            'identity_source' => $source,
            'type' => $c->type?->value,
            'type_label' => $c->type?->label(),
            'cadence' => $c->cadence,
            'chair' => $chair ? array_intersect_key($chair, array_flip(['name', 'entity_id', 'user_id', 'source'])) : null,
            'responsible_agent_id' => $c->responsible_agent_id,
            'signal_set_id' => $c->signal_set_id,
            'status' => $c->status?->value,
            'status_label' => $c->status?->label(),
            'format' => $withFormat ? $format->toArray() : ['key' => $format->key(), 'name' => $format->name(), 'question' => $format->question()],
            // Abwärtskompatibel: Liste der Knoten (genau einer oder keiner)
            'organization_nodes' => $node ? [['entity_id' => $node->id, 'entity_name' => $node->name]] : [],
            'members' => $members->map(fn ($m) => array_intersect_key($m, array_flip(['name', 'role', 'role_label', 'entity_id', 'user_id', 'agent_id', 'is_agent', 'source'])))->values()->all(),
            'members_source' => $source,
            'created_by' => $c->created_by,
            'created_at' => $c->created_at?->toIso8601String(),
            'updated_at' => $c->updated_at?->toIso8601String(),
        ];
    }

    /** Gemeinsame Property-Definitionen für POST/PUT. */
    protected function committeeFieldSchema(): array
    {
        return [
            'name' => ['type' => 'string', 'description' => 'Name des Gremiums. Liegt am Organisations-Knoten – beim Ändern wird der Knoten umbenannt.'],
            'type' => ['type' => 'string', 'enum' => CommitteeType::values(), 'description' => 'Typ: steering_committee (Lenkungsausschuss), strategy_meeting (Strategie-Meeting), board (Board), other (Sonstiges). Bestimmt das Format.'],
            'description' => ['type' => 'string', 'description' => 'Zweck des Gremiums. Liegt am Organisations-Knoten – beim Ändern wird die Knoten-Beschreibung geändert.'],
            'cadence' => ['type' => 'string', 'description' => 'Rhythmus als Freitext/Info, z. B. "monatlich". Keine Ablauflogik.'],
            'responsible_agent_id' => ['type' => 'integer', 'description' => 'Verantwortlicher Agent (User-ID eines Agent-Users), der das Gremium vorbereitet.'],
            'signal_set_id' => ['type' => 'integer', 'description' => 'Optional: Verweis auf ein Signal-Set im Signals-Modul.'],
            'status' => ['type' => 'string', 'enum' => CommitteeStatus::values(), 'description' => 'Status: active, dormant (ruhend), dissolved (aufgelöst).'],
            'organization_entity_id' => ['type' => 'integer', 'description' => 'Organisations-Knoten des Gremiums (Typ „Board / Gremium“). Das Gremium IST dieser Knoten: Name, Beschreibung, Mitglieder und Vorsitz kommen von dort. Ein Knoten trägt höchstens ein Gremium. Beim Ändern: neuer Knoten; 0 löst die Verbindung (nur Übergang).'],
            'members' => [
                'type' => 'array',
                'description' => 'Mitglieder; ersetzt die gesamte Liste (weglassen = unverändert). Mit Knoten werden daraus Beziehungen in der Organisation: '
                    . 'role=chair → „führt an“ (manages) + „ist Teil von“, member → „ist Teil von“ (is_part_of), agent → „ist aktiv in“ (is_active_in). '
                    . 'Je Eintrag entity_id (Personen-/Agenten-Knoten, auch Personen ohne Plattform-Konto) ODER user_id/agent_id (wird über linked_user_id auf den Personen-Knoten abgebildet). Höchstens ein Vorsitz.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'entity_id' => ['type' => 'integer', 'description' => 'Personen- oder Agenten-Knoten der Organisation.'],
                        'user_id' => ['type' => 'integer'],
                        'agent_id' => ['type' => 'integer'],
                        'role' => ['type' => 'string', 'enum' => array_merge(CommitteeMemberRole::values(), ['agent']), 'description' => 'chair (Vorsitz), member (Mitglied), agent (Agent im Gremium). secretary/guest gelten am Knoten als Mitglied. Default member.'],
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

        $userIds = array_filter([$arguments['responsible_agent_id'] ?? null]);
        $roles = array_merge(CommitteeMemberRole::values(), ['agent']);
        foreach ($arguments['members'] ?? [] as $i => $m) {
            if (! is_array($m)) {
                return "members[{$i}] muss ein Objekt sein.";
            }
            $given = array_filter([$m['entity_id'] ?? null, $m['user_id'] ?? null, $m['agent_id'] ?? null]);
            if (count($given) !== 1) {
                return "members[{$i}]: genau eines von entity_id, user_id oder agent_id angeben.";
            }
            if (isset($m['role']) && ! in_array((string) $m['role'], $roles, true)) {
                return "members[{$i}].role muss einer von " . implode(', ', $roles) . ' sein.';
            }
            if (! empty($m['user_id']) || ! empty($m['agent_id'])) {
                $userIds[] = $m['user_id'] ?? $m['agent_id'];
            }
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

    /**
     * Prüft organization_entity_id: existiert, ist ein Gremien-Knoten, ist frei, Schreibrecht.
     *
     * @return string|null Fehlermeldung
     */
    protected function validatePlacement(array $arguments, ToolContext $context, ?ManagementBookCommittee $committee = null, ?int $teamId = null): ?string
    {
        if (! array_key_exists('organization_entity_id', $arguments) || $arguments['organization_entity_id'] === null) {
            return null;
        }
        $entityId = (int) $arguments['organization_entity_id'];
        if ($entityId === 0) {
            return null;
        }
        $service = $this->nodes();
        if (! $service->available()) {
            return 'Organisations-Modul nicht verfügbar – Verortung nicht möglich.';
        }
        $entity = \Platform\Organization\Models\OrganizationEntity::with('type:id,code,name')->find($entityId);
        if (! $entity) {
            return "Knoten #{$entityId} nicht gefunden.";
        }
        if ($service->committeeTypeId() && ! $service->isCommitteeNode($entity)) {
            return "Knoten #{$entityId} („{$entity->name}“, Typ {$entity->type?->name}) ist kein Gremien-Knoten (Typ „Board / Gremium“). Nutze create_node, um einen anzulegen.";
        }
        $teamId = $teamId ?? (int) ($committee?->team_id ?? $context->team?->id);
        if ($other = $service->committeeAt($entityId, $teamId, $committee?->id)) {
            return "Knoten #{$entityId} ist bereits das Gremium #{$other->id}. Ein Knoten trägt höchstens ein Gremium.";
        }

        return $this->canWriteEntity($context, $entityId) ? null : "Kein Schreibrecht auf Knoten #{$entityId}.";
    }

    /** Wendet organization_entity_id an (0 = lösen). */
    protected function applyPlacement(ManagementBookCommittee $committee, array $arguments): void
    {
        if (! array_key_exists('organization_entity_id', $arguments) || $arguments['organization_entity_id'] === null) {
            return;
        }
        $entityId = (int) $arguments['organization_entity_id'];
        $entityId === 0 ? $this->nodes()->unplace($committee) : $this->nodes()->place($committee, $entityId);
    }

    /**
     * Schreibt Name/Beschreibung: mit Knoten in den Knoten (Schreibrecht nötig), sonst in die Book-Spalten.
     *
     * @return string|null Fehlermeldung
     */
    protected function applyIdentity(ManagementBookCommittee $committee, array $arguments, ToolContext $context): ?string
    {
        $hasName = array_key_exists('name', $arguments);
        $hasDescription = array_key_exists('description', $arguments);
        if (! $hasName && ! $hasDescription) {
            return null;
        }
        $nodeId = $this->nodes()->available() ? $this->nodes()->nodeId($committee) : null;
        if (! $nodeId) {
            $committee->update(array_intersect_key($arguments, array_flip(['name', 'description'])));

            return null;
        }
        if (! $this->canWriteEntity($context, $nodeId)) {
            return "Name/Beschreibung liegen am Organisations-Knoten #{$nodeId} – dafür fehlt das Schreibrecht.";
        }
        $this->nodes()->updateNodeText($committee, $hasName ? (string) $arguments['name'] : null, $hasDescription ? ($arguments['description'] ?? null) : null, $hasDescription);
        // Cache im Book nachziehen (Fallback, falls die Organisation einmal fehlt)
        $committee->forceFill(array_filter([
            'name' => $hasName ? trim((string) $arguments['name']) : null,
        ]) + ($hasDescription ? ['description' => $arguments['description'] ?? null] : []))->saveQuietly();

        return null;
    }

    /**
     * Ersetzt die Mitglieder. Mit Knoten: Beziehungen in der Organisation (Schreibrecht nötig); sonst Book-Liste (Übergang).
     *
     * @return string|null Fehlermeldung
     */
    protected function syncMembers(ManagementBookCommittee $committee, array $members, ?ToolContext $context = null): ?string
    {
        $service = $this->nodes();
        $nodeId = $service->available() ? $service->nodeId($committee) : null;
        if ($nodeId) {
            if ($context && ! $this->canWriteEntity($context, $nodeId)) {
                return "Mitglieder liegen am Organisations-Knoten #{$nodeId} – dafür fehlt das Schreibrecht.";
            }

            return $service->syncMembers($committee, $members);
        }

        $committee->members()->delete();
        $chair = collect($members)->first(fn ($m) => ($m['role'] ?? null) === 'chair' && ! empty($m['user_id']));
        $committee->forceFill(['chair_user_id' => $chair ? (int) $chair['user_id'] : null])->saveQuietly();
        $seen = [];
        foreach ($members as $m) {
            if (empty($m['user_id']) && empty($m['agent_id'])) {
                continue;
            }
            $key = ! empty($m['user_id']) ? 'u' . (int) $m['user_id'] : 'a' . (int) $m['agent_id'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $role = CommitteeMemberRole::tryFrom((string) ($m['role'] ?? '')) ?? CommitteeMemberRole::MEMBER;
            $committee->members()->create([
                'user_id' => ! empty($m['user_id']) ? (int) $m['user_id'] : null,
                'agent_id' => ! empty($m['agent_id']) ? (int) $m['agent_id'] : null,
                'role' => $role->value,
            ]);
        }
        $committee->unsetRelation('members');

        return null;
    }

    /** Schreibrecht für den Parent eines neuen Gremien-Knotens (Default: Einheit des Books). */
    protected function validateCreateNode(array $spec, ManagementBook $book, ToolContext $context): ?string
    {
        if (trim((string) ($spec['name'] ?? '')) === '') {
            return 'create_node.name ist erforderlich.';
        }
        $service = $this->nodes();
        if (! $service->available()) {
            return 'Organisations-Modul nicht verfügbar – create_node nicht möglich.';
        }
        if (! $service->committeeTypeId()) {
            return 'In der Organisation gibt es keinen Knoten-Typ „Board / Gremium“ (Code board).';
        }
        $parentId = (int) ($spec['parent_entity_id'] ?? 0) ?: ($service->unitNodeIds($book)[0] ?? 0);
        if (! $parentId) {
            return 'Das Book hängt an keinem Organisations-Knoten – create_node.parent_entity_id angeben.';
        }

        return $this->canWriteEntity($context, $parentId) ? null : "Kein Schreibrecht auf Knoten #{$parentId} (Parent des neuen Gremien-Knotens).";
    }
}
