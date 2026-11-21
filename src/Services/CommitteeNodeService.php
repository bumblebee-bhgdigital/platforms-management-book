<?php

namespace Platform\ManagementBook\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Platform\Core\Models\User;
use Platform\ManagementBook\Enums\CommitteeMemberRole;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Models\OrganizationEntityRelationType;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Services\EntityDimensionBridge;

/**
 * Gremium = Organisations-Knoten. Das Management Book ist das „Betriebssystem“ des Gremiums (Format, Sitzungen,
 * Beschlüsse); Identität, Name, Beschreibung, Mitglieder und Vorsitz liegen am Knoten:
 *
 * - Verortung: genau ein Knoten je Gremium (Spalte organization_entity_id, eindeutig je Team). Die Dimension-Bridge-
 *   Verknüpfung (Alias management_book_committee) wird aus der Spalte gepflegt, damit Links/Kennzahlen/Zeiten in der
 *   Organisation funktionieren.
 * - Mitglieder & Vorsitz: Beziehungen am Knoten – Person „ist Teil von“ (is_part_of) → Mitglied, „führt an“ (manages)
 *   → Vorsitz, Agent „ist aktiv in“ (is_active_in) → Agent im Gremium. Plattform-Konto über linked_user_id am
 *   Personen-/Agenten-Knoten (Personen ohne Konto sind vollwertige Mitglieder).
 * - Ohne Knoten (Übergang) bzw. ohne Organisations-Modul: Fallback auf Name/Beschreibung/Mitgliederliste im Book.
 */
class CommitteeNodeService
{
    public const REL_MEMBER = 'is_part_of';
    public const REL_CHAIR = 'manages';
    public const REL_AGENT = 'is_active_in';

    /** Entity-Type-Codes für Gremien-Knoten („Board / Gremium“). */
    public const COMMITTEE_TYPE_CODES = ['board', 'committee'];

    public function available(): bool
    {
        return class_exists(EntityDimensionBridge::class) && class_exists(OrganizationEntity::class);
    }

    /**
     * Gremien als Knoten führen? Nur wenn das Organisations-Modul da ist UND es den Knoten-Typ „Board / Gremium“ gibt
     * (sonst ist die Organisation nicht eingerichtet → Gremium lebt übergangsweise nur im Book).
     */
    public function nodeMode(): bool
    {
        return $this->available() && $this->committeeTypeId() !== null;
    }

    // ── Verortung ────────────────────────────────────────────

    public function nodeId(ManagementBookCommittee $committee): ?int
    {
        if ($committee->organization_entity_id) {
            return (int) $committee->organization_entity_id;
        }

        return $this->bridgeNodeIds($committee)[0] ?? null;
    }

    /** @return int[] */
    public function nodeIds(ManagementBookCommittee $committee): array
    {
        $id = $this->nodeId($committee);

        return $id ? [$id] : [];
    }

    public function node(ManagementBookCommittee $committee): ?OrganizationEntity
    {
        if (! $this->available() || ! ($id = $this->nodeId($committee))) {
            return null;
        }

        return OrganizationEntity::with('type:id,name,code')->find($id);
    }

    /** Gremium, das an $entityId hängt (je Team eindeutig). */
    public function committeeAt(int $entityId, int $teamId, ?int $exceptId = null): ?ManagementBookCommittee
    {
        return ManagementBookCommittee::where('team_id', $teamId)->where('organization_entity_id', $entityId)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->first();
    }

    /**
     * Gremium an $entityId hängen (ersetzt eine bestehende Verortung).
     *
     * @throws \DomainException wenn der Knoten schon ein anderes Gremium trägt
     */
    public function place(ManagementBookCommittee $committee, int $entityId): void
    {
        if ($other = $this->committeeAt($entityId, (int) $committee->team_id, $committee->id)) {
            throw new \DomainException("Knoten #{$entityId} ist bereits das Gremium „{$other->getRawOriginal('name')}“ (#{$other->id}). Ein Knoten kann nur ein Gremium tragen.");
        }

        $this->setNodeColumn($committee, $entityId);

        if (! $this->available()) {
            return;
        }
        if ($this->bridgeNodeIds($committee) === []) {
            EntityDimensionBridge::createLink($entityId, ManagementBookCommittee::MORPH_ALIAS, $committee->id, ['team_id' => $committee->team_id]);
        } else {
            EntityDimensionBridge::replaceLinks(ManagementBookCommittee::MORPH_ALIAS, $committee->id, $entityId, ['team_id' => $committee->team_id]);
        }
        EntityDimensionBridge::flush();
    }

    /** Verortung lösen (Spalte und alle Bridge-Links). */
    public function unplace(ManagementBookCommittee $committee): void
    {
        $this->setNodeColumn($committee, null);
        if (! $this->available()) {
            return;
        }
        foreach ($this->bridgeNodeIds($committee) as $entityId) {
            EntityDimensionBridge::deleteLink($entityId, ManagementBookCommittee::MORPH_ALIAS, $committee->id);
        }
        EntityDimensionBridge::flush();
    }

    /**
     * Spalte aus der Bridge nachziehen (Verknüpfung entstand extern, z. B. organization.entity.link.POST).
     * Belegt ein anderes Gremium den Knoten schon, bleibt die Spalte unverändert.
     */
    public function syncFromBridge(int $committeeId): void
    {
        $committee = ManagementBookCommittee::find($committeeId);
        if (! $committee || ! $this->available()) {
            return;
        }
        EntityDimensionBridge::flush();
        $ids = $this->bridgeNodeIds($committee);
        $target = in_array((int) $committee->organization_entity_id, $ids, true) ? (int) $committee->organization_entity_id : ($ids[0] ?? null);
        if ($target && $this->committeeAt($target, (int) $committee->team_id, $committee->id)) {
            return;
        }
        if ((int) $committee->organization_entity_id !== (int) $target) {
            $this->setNodeColumn($committee, $target);
        }
    }

    /** Spalte direkt schreiben (unabhängig vom Dirty-Stand der übergebenen Instanz) und Instanz nachziehen. */
    private function setNodeColumn(ManagementBookCommittee $committee, ?int $entityId): void
    {
        ManagementBookCommittee::withTrashed()->whereKey($committee->id)->update(['organization_entity_id' => $entityId]);
        $committee->setAttribute('organization_entity_id', $entityId);
        $committee->syncOriginalAttribute('organization_entity_id');
        $committee->unsetRelation('organizationNode');
    }

    /** @return int[] */
    private function bridgeNodeIds(ManagementBookCommittee $committee): array
    {
        if (! $this->available() || ! $committee->id) {
            return [];
        }

        return EntityDimensionBridge::linksForLinkables([ManagementBookCommittee::MORPH_ALIAS], [$committee->id], false)
            ->pluck('entity_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    // ── Knoten-Typen, Einheit, Governance ────────────────────

    public function committeeTypeId(): ?int
    {
        if (! $this->available()) {
            return null;
        }
        $types = OrganizationEntityType::whereIn('code', self::COMMITTEE_TYPE_CODES)->get(['id', 'code']);

        return ($types->firstWhere('code', self::COMMITTEE_TYPE_CODES[0]) ?? $types->first())?->id;
    }

    public function isCommitteeNode(OrganizationEntity $entity): bool
    {
        return in_array($entity->type?->code ?? OrganizationEntityType::whereKey($entity->entity_type_id)->value('code'), self::COMMITTEE_TYPE_CODES, true);
    }

    /** Knoten, an denen das Book hängt (die Einheit, z. B. BHG.DIGITAL). @return int[] */
    public function unitNodeIds(ManagementBook $book): array
    {
        if (! $this->available()) {
            return [];
        }

        return EntityDimensionBridge::linksForLinkables([ManagementBook::MORPH_ALIAS], [$book->id], false)
            ->pluck('entity_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** Container „Governance“ direkt unter $unitId; legt ihn auf Wunsch an. */
    public function governanceContainer(int $unitId, bool $create = true): ?OrganizationEntity
    {
        $containerTypeIds = OrganizationEntityType::query()
            ->where(fn ($q) => $q->where('code', 'container')->orWhere('name', 'Container'))
            ->pluck('id');

        $existing = OrganizationEntity::where('parent_entity_id', $unitId)
            ->where(fn ($q) => $q->where('name', 'Governance')->orWhere('code', 'like', '%GOVERNANCE'))
            ->when($containerTypeIds->isNotEmpty(), fn ($q) => $q->whereIn('entity_type_id', $containerTypeIds))
            ->orderBy('id')->first();
        if ($existing || ! $create || $containerTypeIds->isEmpty()) {
            return $existing;
        }

        $unit = OrganizationEntity::findOrFail($unitId);

        return OrganizationEntity::create([
            'name' => 'Governance',
            'description' => 'Sammel-Container für die Gremien der Einheit.',
            'team_id' => $unit->team_id,
            'user_id' => Auth::id() ?? $unit->user_id,
            'entity_type_id' => $containerTypeIds->first(),
            'parent_entity_id' => $unitId,
            'is_active' => true,
        ]);
    }

    /**
     * Legt einen Gremien-Knoten an. Default-Parent: Container „Governance“ unter der Einheit des Books
     * (fehlt der Container-Typ, direkt unter der Einheit).
     *
     * @throws \DomainException wenn kein Parent bestimmbar oder kein Gremien-Typ vorhanden ist
     */
    public function createNode(ManagementBook $book, string $name, ?int $parentEntityId = null, ?string $description = null): OrganizationEntity
    {
        if (! $this->available()) {
            throw new \DomainException('Organisations-Modul nicht verfügbar.');
        }
        $typeId = $this->committeeTypeId();
        if (! $typeId) {
            throw new \DomainException('In der Organisation gibt es keinen Knoten-Typ „Board / Gremium“ (Code board).');
        }
        if (! $parentEntityId) {
            $unitId = $this->unitNodeIds($book)[0] ?? null;
            if (! $unitId) {
                throw new \DomainException('Das Book hängt an keinem Organisations-Knoten – bitte parent_entity_id angeben oder das Book zuerst verorten.');
            }
            $parentEntityId = $this->governanceContainer($unitId)?->id ?? $unitId;
        }
        $parent = OrganizationEntity::findOrFail($parentEntityId);

        return OrganizationEntity::create([
            'name' => trim($name),
            'description' => $description,
            'team_id' => $parent->team_id,
            'user_id' => Auth::id() ?? $parent->user_id,
            'entity_type_id' => $typeId,
            'parent_entity_id' => $parent->id,
            'is_active' => true,
        ]);
    }

    /**
     * Gremien-Knoten unterhalb der Einheit(en) des Books, die noch kein Gremium sind (Vorschläge zum Aktivieren).
     *
     * @return Collection<int, OrganizationEntity>
     */
    public function suggestions(ManagementBook $book): Collection
    {
        $typeIds = $this->available() ? OrganizationEntityType::whereIn('code', self::COMMITTEE_TYPE_CODES)->pluck('id') : collect();
        $units = $this->unitNodeIds($book);
        if ($typeIds->isEmpty() || $units === []) {
            return collect();
        }

        // Nachfahren bis Tiefe 4 (Einheit → Governance → Gremium reicht meist)
        $ids = [];
        $frontier = $units;
        for ($depth = 0; $frontier !== [] && $depth < 4; $depth++) {
            $frontier = OrganizationEntity::whereIn('parent_entity_id', $frontier)->where('is_active', true)->pluck('id')->map(fn ($i) => (int) $i)->diff($ids)->values()->all();
            $ids = array_merge($ids, $frontier);
        }
        if ($ids === []) {
            return collect();
        }
        $taken = ManagementBookCommittee::where('team_id', $book->team_id)->whereNotNull('organization_entity_id')->pluck('organization_entity_id')->map(fn ($i) => (int) $i)->all();

        return OrganizationEntity::with('parent:id,name')->whereIn('id', $ids)->whereIn('entity_type_id', $typeIds)
            ->whereNotIn('id', $taken)->orderBy('name')->get(['id', 'name', 'description', 'parent_entity_id']);
    }

    // ── Name & Beschreibung ──────────────────────────────────

    /** Schreibt Name/Beschreibung in den Knoten (Single Source). */
    public function updateNodeText(ManagementBookCommittee $committee, ?string $name, ?string $description, bool $setDescription): void
    {
        $node = $this->node($committee);
        if (! $node) {
            return;
        }
        $data = [];
        if ($name !== null && trim($name) !== '') {
            $data['name'] = trim($name);
        }
        if ($setDescription) {
            $data['description'] = $description;
        }
        if ($data !== []) {
            $node->update($data);
            $committee->unsetRelation('organizationNode');
        }
    }

    // ── Mitglieder & Vorsitz ─────────────────────────────────

    /**
     * Mitglieder des Gremiums. Mit Knoten aus den Beziehungen der Organisation, sonst aus der Book-Liste.
     * Je Eintrag: key, name, role (chair|member|agent|secretary|guest), entity_id, user_id, agent_id, source.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function members(ManagementBookCommittee $committee): Collection
    {
        $nodeId = $this->available() ? $this->nodeId($committee) : null;
        if (! $nodeId) {
            return $this->legacyMembers($committee);
        }

        $typeIds = $this->relationTypeIds();
        if ($typeIds === []) {
            return collect();
        }
        $codeOf = array_flip($typeIds);
        $rels = OrganizationEntityRelationship::query()
            ->where('to_entity_id', $nodeId)
            ->whereIn('relation_type_id', array_values($typeIds))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()->toDateString()))
            ->with(['fromEntity' => fn ($q) => $q->with('type:id,code')->select(['id', 'name', 'linked_user_id', 'entity_type_id', 'is_active'])])
            ->get();

        $userTypes = User::whereIn('id', $rels->pluck('fromEntity.linked_user_id')->filter()->unique())->pluck('type', 'id');
        $order = ['chair' => 0, 'member' => 1, 'agent' => 2];

        return $rels->filter(fn ($r) => $r->fromEntity)->groupBy('from_entity_id')->map(function (Collection $group) use ($codeOf, $userTypes) {
            $entity = $group->first()->fromEntity;
            $codes = $group->map(fn ($r) => $codeOf[$r->relation_type_id] ?? null)->filter()->all();
            $userId = $entity->linked_user_id ? (int) $entity->linked_user_id : null;
            $isAgent = ($entity->type?->code === 'agent') || ($userId && ($userTypes[$userId] ?? null) === 'ai_user');
            $role = in_array(self::REL_CHAIR, $codes, true) ? 'chair' : ($isAgent && in_array(self::REL_AGENT, $codes, true) && ! in_array(self::REL_MEMBER, $codes, true) ? 'agent' : 'member');

            return [
                'key' => 'e' . $entity->id,
                'name' => $entity->name,
                'role' => $role,
                'role_label' => $this->roleLabel($role),
                'entity_id' => (int) $entity->id,
                'user_id' => $isAgent ? null : $userId,
                'agent_id' => $isAgent ? $userId : null,
                'is_agent' => $isAgent,
                'relations' => array_values(array_unique($codes)),
                'source' => 'organization',
            ];
        })->sortBy(fn ($m) => [$order[$m['role']] ?? 9, $m['name']])->values();
    }

    /** Vorsitz (erste Person mit „führt an“) bzw. chair_user_id im Book-Fallback. */
    public function chair(ManagementBookCommittee $committee): ?array
    {
        $chair = $this->members($committee)->firstWhere('role', 'chair');
        if ($chair) {
            return $chair;
        }
        if (! $this->nodeId($committee) && $committee->chair_user_id) {
            $name = User::whereKey($committee->chair_user_id)->value('name');

            return ['key' => 'u' . $committee->chair_user_id, 'name' => $name, 'role' => 'chair', 'role_label' => $this->roleLabel('chair'),
                'entity_id' => null, 'user_id' => (int) $committee->chair_user_id, 'agent_id' => null, 'is_agent' => false, 'relations' => [], 'source' => 'book'];
        }

        return null;
    }

    /**
     * Person/Agent zum Gremium hinzufügen bzw. Rolle setzen – schreibt Beziehungen am Knoten.
     * chair: is_part_of + manages (andere „führt an“ am Knoten werden gelöst – ein Vorsitz); member: is_part_of; agent: is_active_in.
     *
     * @throws \DomainException ohne Knoten
     */
    public function setMember(ManagementBookCommittee $committee, int $personEntityId, string $role): void
    {
        $nodeId = $this->requireNode($committee);
        $role = in_array($role, ['chair', 'member', 'agent'], true) ? $role : 'member';
        $person = OrganizationEntity::findOrFail($personEntityId);
        abort_if($person->id === $nodeId, 422, 'Das Gremium kann nicht Mitglied von sich selbst sein.');

        $want = match ($role) {
            'chair' => [self::REL_MEMBER, self::REL_CHAIR],
            'agent' => [self::REL_AGENT],
            default => [self::REL_MEMBER],
        };
        $types = $this->relationTypeIds();
        foreach ([self::REL_MEMBER, self::REL_CHAIR, self::REL_AGENT] as $code) {
            if (! isset($types[$code])) {
                continue;
            }
            $existing = OrganizationEntityRelationship::where('from_entity_id', $person->id)->where('to_entity_id', $nodeId)->where('relation_type_id', $types[$code]);
            if (in_array($code, $want, true)) {
                if (! $existing->exists()) {
                    OrganizationEntityRelationship::create([
                        'from_entity_id' => $person->id, 'to_entity_id' => $nodeId, 'relation_type_id' => $types[$code],
                        'team_id' => $person->team_id, 'user_id' => Auth::id() ?? $person->user_id,
                    ]);
                }
            } else {
                $existing->get()->each->delete();
            }
        }

        if ($role === 'chair' && isset($types[self::REL_CHAIR])) {
            OrganizationEntityRelationship::where('to_entity_id', $nodeId)->where('relation_type_id', $types[self::REL_CHAIR])
                ->where('from_entity_id', '!=', $person->id)->get()->each->delete();
        }
    }

    /** Person/Agent aus dem Gremium nehmen (löst is_part_of, manages, is_active_in zum Knoten). */
    public function removeMember(ManagementBookCommittee $committee, int $personEntityId): void
    {
        $nodeId = $this->requireNode($committee);
        OrganizationEntityRelationship::where('from_entity_id', $personEntityId)->where('to_entity_id', $nodeId)
            ->whereIn('relation_type_id', array_values($this->relationTypeIds()))->get()->each->delete();
    }

    /**
     * Ersetzt die Mitglieder am Knoten. Einträge: entity_id ODER user_id/agent_id (→ Personen-/Agenten-Knoten über
     * linked_user_id) plus role (chair|member|agent; secretary/guest → member).
     *
     * @return string|null Fehlermeldung
     */
    public function syncMembers(ManagementBookCommittee $committee, array $entries): ?string
    {
        $resolved = [];
        foreach ($entries as $i => $e) {
            $entityId = $this->resolvePersonEntityId($e);
            if (! $entityId) {
                return "members[{$i}]: kein Personen-/Agenten-Knoten gefunden (entity_id angeben oder Knoten mit linked_user_id anlegen).";
            }
            $role = (string) ($e['role'] ?? 'member');
            $resolved[$entityId] = match (true) {
                $role === 'chair' => 'chair',
                $role === 'agent' || ! empty($e['agent_id']) => 'agent',
                default => 'member',
            };
        }
        if (count(array_keys($resolved, 'chair', true)) > 1) {
            return 'Höchstens ein Vorsitz (role=chair).';
        }

        foreach ($this->members($committee) as $m) {
            if (! isset($resolved[$m['entity_id']])) {
                $this->removeMember($committee, $m['entity_id']);
            }
        }
        foreach ($resolved as $entityId => $role) {
            $this->setMember($committee, $entityId, $role);
        }

        return null;
    }

    /** Personen-/Agenten-Knoten zu einem Eintrag (entity_id direkt oder über linked_user_id). */
    public function resolvePersonEntityId(array $entry): ?int
    {
        if (! empty($entry['entity_id'])) {
            return OrganizationEntity::whereKey((int) $entry['entity_id'])->exists() ? (int) $entry['entity_id'] : null;
        }
        $userId = (int) ($entry['user_id'] ?? $entry['agent_id'] ?? 0);

        return $userId ? $this->personEntityIdForUser($userId) : null;
    }

    public function personEntityIdForUser(int $userId): ?int
    {
        if (! $this->available()) {
            return null;
        }
        $id = OrganizationEntity::where('linked_user_id', $userId)->where('is_active', true)
            ->orderByRaw("CASE WHEN entity_type_id IN (SELECT id FROM organization_entity_types WHERE code IN ('person','agent')) THEN 0 ELSE 1 END")
            ->orderBy('id')->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Personen-/Agenten-Knoten als Auswahl (für „Mitglied hinzufügen“).
     *
     * @return array<int, array{value:int,label:string}>
     */
    public function personOptions(int $teamId, string $search = ''): array
    {
        if (! $this->available()) {
            return [];
        }
        $typeIds = OrganizationEntityType::whereIn('code', ['person', 'agent'])->pluck('id');

        return OrganizationEntity::where('is_active', true)->whereIn('entity_type_id', $typeIds)
            ->when(trim($search) !== '', fn ($q) => $q->where('name', 'like', '%' . trim($search) . '%'))
            ->orderBy('name')->limit(200)->get(['id', 'name'])
            ->map(fn ($e) => ['value' => (int) $e->id, 'label' => $e->name])->all();
    }

    public function nodeUrl(?int $entityId): ?string
    {
        return $entityId && Route::has('organization.entities.show') ? route('organization.entities.show', $entityId) : null;
    }

    public function roleLabel(string $role): string
    {
        return $role === 'agent' ? 'Agent' : (CommitteeMemberRole::tryFrom($role)?->label() ?? $role);
    }

    // ── intern ───────────────────────────────────────────────

    /** @return array<string,int> code → relation_type_id */
    private function relationTypeIds(): array
    {
        if (! class_exists(OrganizationEntityRelationType::class)) {
            return [];
        }

        return OrganizationEntityRelationType::whereIn('code', [self::REL_MEMBER, self::REL_CHAIR, self::REL_AGENT])
            ->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();
    }

    private function requireNode(ManagementBookCommittee $committee): int
    {
        $nodeId = $this->available() ? $this->nodeId($committee) : null;
        if (! $nodeId) {
            throw new \DomainException('Das Gremium hängt an keinem Organisations-Knoten – Mitglieder werden dort als Beziehungen geführt.');
        }

        return $nodeId;
    }

    /** Book-Mitgliederliste (Übergang, Gremien ohne Knoten). */
    private function legacyMembers(ManagementBookCommittee $committee): Collection
    {
        $committee->loadMissing(['members.user:id,name', 'members.agent:id,name']);
        $order = ['chair' => 0, 'secretary' => 1, 'member' => 2, 'guest' => 3];

        return $committee->members->map(fn ($m) => [
            'key' => 'm' . $m->id,
            'name' => $m->user?->name ?? $m->agent?->name ?? '–',
            'role' => $m->role?->value ?? 'member',
            'role_label' => $m->role?->label() ?? 'Mitglied',
            'entity_id' => null,
            'user_id' => $m->user_id ? (int) $m->user_id : null,
            'agent_id' => $m->agent_id ? (int) $m->agent_id : null,
            'is_agent' => (bool) $m->agent_id,
            'relations' => [],
            'source' => 'book',
            'member_id' => $m->id,
        ])->sortBy(fn ($m) => [$order[$m['role']] ?? 9, $m['name']])->values();
    }
}
