<?php

namespace Platform\ManagementBook\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Models\ManagementBookCommittee;
use Platform\ManagementBook\Tools\Concerns\HandlesCommittees;

class UpdateCommitteeTool implements ToolContract, ToolMetadataContract
{
    use HandlesCommittees;

    public function getName(): string
    {
        return 'management-book.committees.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /management-book/committees/{id} - Ändert ein Gremium. Pflicht: id. '
            . 'Felder: name, type, description, cadence, chair_user_id, responsible_agent_id, signal_set_id, status (active/dormant/dissolved), '
            . 'organization_entity_id (Verortung am Organisations-Knoten, 0 = lösen). Der Typ bestimmt das Format (Agenda-Felder, Arten, Erfolgsmessung). '
            . 'members[] ersetzt die komplette Mitgliederliste (weglassen = unverändert, [] = alle entfernen). Nicht übergebene Felder bleiben unverändert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->committeeFieldSchema(), [
                'id' => ['type' => 'integer', 'description' => 'ID des Gremiums (ERFORDERLICH).'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ]),
            'required' => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            if (empty($arguments['id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'id ist erforderlich.');
            }
            $committee = ManagementBookCommittee::query()->where('team_id', $team->id)->find((int) $arguments['id']);
            if (! $committee) {
                return ToolResult::error('NOT_FOUND', 'Gremium nicht gefunden.');
            }
            if ($denied = $this->authorizeCommittee($context, 'update', $committee)) {
                return $denied;
            }
            if (array_key_exists('name', $arguments) && trim((string) $arguments['name']) === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
            }
            if ($message = $this->validateCommitteeInput($arguments, $team->id) ?? $this->validatePlacement($arguments, $context)) {
                return ToolResult::error('VALIDATION_ERROR', $message);
            }

            DB::transaction(function () use ($arguments, $committee) {
                $fields = array_intersect_key($arguments, array_flip(['name', 'type', 'description', 'cadence', 'chair_user_id', 'responsible_agent_id', 'signal_set_id', 'status']));
                if ($fields !== []) {
                    $committee->update($fields);
                }
                if (isset($arguments['members']) && is_array($arguments['members'])) {
                    $this->syncMembers($committee, $arguments['members']);
                }
                $this->applyPlacement($committee, $arguments);
            });

            return ToolResult::success([
                'committee' => $this->committeeToArray($committee->fresh(), true),
                'message' => "Gremium #{$committee->id} aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['management-book', 'committees', 'update', 'members'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
