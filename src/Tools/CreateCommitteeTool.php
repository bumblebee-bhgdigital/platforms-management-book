<?php

namespace Platform\ManagementBook\Tools;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\ManagementBook\Tools\Concerns\HandlesCommittees;

class CreateCommitteeTool implements ToolContract, ToolMetadataContract
{
    use HandlesCommittees;

    public function getName(): string
    {
        return 'management-book.committees.POST';
    }

    public function getDescription(): string
    {
        return 'POST /management-book/committees - Macht einen Organisations-Knoten zum Gremium eines Management Books. Das Gremium IST der Knoten: '
            . 'Name, Beschreibung, Mitglieder und Vorsitz liegen dort; das Book ergänzt Typ/Format, Rhythmus, Sitzungen und Beschlüsse. '
            . 'Pflicht: management_book_id und ENTWEDER organization_entity_id (bestehender Gremien-Knoten, Typ „Board / Gremium“; frei, d. h. noch kein Gremium) '
            . 'ODER create_node {name, parent_entity_id?, description?} – legt den Knoten an (Default-Parent: Container „Governance“ unter der Einheit des Books, wird bei Bedarf angelegt). '
            . 'Nicht doppelt anlegen: vorhandene Gremien-Knoten findest du mit organization.entities.GET. '
            . 'Optional: type (steering_committee: Regulation/vier Fragen, strategy_meeting: Richtung, board: Kontrolle & Freigabe, other), cadence, status, responsible_agent_id, signal_set_id, '
            . 'members[] (schreibt Beziehungen am Knoten, siehe Schema). Ohne eingerichtete Organisation (kein Knoten-Typ „Board / Gremium“) genügt name.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->committeeFieldSchema(), [
                'management_book_id' => ['type' => 'integer', 'description' => 'Management Book, zu dem das Gremium gehört (ERFORDERLICH).'],
                'create_node' => [
                    'type' => 'object',
                    'description' => 'Statt organization_entity_id: neuen Gremien-Knoten anlegen.',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Name des Gremiums, z. B. „BHG.DIGITAL Strategiemeeting“ (ERFORDERLICH).'],
                        'parent_entity_id' => ['type' => 'integer', 'description' => 'Optional: Parent-Knoten. Default: Container „Governance“ unter der Einheit des Books.'],
                        'description' => ['type' => 'string', 'description' => 'Optional: Beschreibung am Knoten.'],
                    ],
                ],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ]),
            'required' => ['management_book_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            if (empty($arguments['management_book_id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'management_book_id ist erforderlich.');
            }
            $book = ManagementBook::query()->where('team_id', $team->id)->find((int) $arguments['management_book_id']);
            if (! $book) {
                return ToolResult::error('NOT_FOUND', 'Management Book nicht gefunden.');
            }
            if (! Gate::forUser($context->user)->allows('update', $book)) {
                return ToolResult::error('ACCESS_DENIED', "Keine Berechtigung, Gremien an Management Book #{$book->id} anzulegen.");
            }

            $service = $this->nodes();
            $createNode = is_array($arguments['create_node'] ?? null) ? $arguments['create_node'] : null;
            $entityId = (int) ($arguments['organization_entity_id'] ?? 0);
            if ($createNode && $entityId) {
                return ToolResult::error('VALIDATION_ERROR', 'Entweder organization_entity_id oder create_node angeben, nicht beides.');
            }
            if ($service->nodeMode() && ! $createNode && ! $entityId) {
                return ToolResult::error('VALIDATION_ERROR', 'Das Gremium ist ein Organisations-Knoten: organization_entity_id (bestehender Gremien-Knoten) oder create_node {name} angeben.');
            }
            if (! $service->nodeMode() && ! $createNode && ! $entityId && trim((string) ($arguments['name'] ?? '')) === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich (Organisation ohne Gremien-Knoten-Typ).');
            }

            $message = $this->validateCommitteeInput($arguments, $team->id)
                ?? ($createNode ? $this->validateCreateNode($createNode, $book, $context) : $this->validatePlacement($arguments, $context, null, $team->id));
            if ($message) {
                return ToolResult::error('VALIDATION_ERROR', $message);
            }

            $result = DB::transaction(function () use ($arguments, $team, $book, $context, $service, $createNode, $entityId) {
                if ($createNode) {
                    $node = $service->createNode($book, (string) $createNode['name'], ! empty($createNode['parent_entity_id']) ? (int) $createNode['parent_entity_id'] : null, $createNode['description'] ?? null);
                    $entityId = $node->id;
                }
                $node = $entityId ? \Platform\Organization\Models\OrganizationEntity::find($entityId) : null;

                $committee = ManagementBookCommittee::create([
                    'team_id' => $team->id,
                    'management_book_id' => $book->id,
                    // Cache/Fallback – maßgeblich ist der Knoten
                    'name' => $node?->name ?? trim((string) $arguments['name']),
                    'description' => $node ? $node->description : ($arguments['description'] ?? null),
                    'type' => $arguments['type'] ?? null,
                    'cadence' => $arguments['cadence'] ?? null,
                    'responsible_agent_id' => $arguments['responsible_agent_id'] ?? null,
                    'signal_set_id' => $arguments['signal_set_id'] ?? null,
                    'status' => $arguments['status'] ?? null,
                    'created_by' => $context->user->id,
                ]);
                if ($entityId) {
                    $service->place($committee, $entityId);
                }
                if (isset($arguments['members']) && is_array($arguments['members'])) {
                    if ($err = $this->syncMembers($committee, $arguments['members'], $context)) {
                        throw new \DomainException($err);
                    }
                }

                return $committee;
            });

            return ToolResult::success([
                'committee' => $this->committeeToArray($result->fresh(), true),
                'message' => "Gremium '{$result->fresh()->name}' (#{$result->id}) an Management Book #{$book->id} angelegt"
                    . ($result->organization_entity_id ? " – Knoten #{$result->organization_entity_id}." : '.'),
            ]);
        } catch (\DomainException $e) {
            return ToolResult::error('VALIDATION_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['management-book', 'committees', 'create', 'organization'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
