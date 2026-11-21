<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Models\ManagementBookRisk;
use Platform\ManagementBook\Tools\Concerns\HandlesRisks;

class UpdateRiskTool implements ToolContract, ToolMetadataContract
{
    use HandlesRisks;

    public function getName(): string
    {
        return 'management-book.risks.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /management-book/risks/{id} - Ändert ein Risiko. Pflicht: id. Alle Felder frei schreibbar (auch status), '
            . 'nicht übergebene Felder bleiben unverändert, null leert ein Feld. Score und Ampel werden nach E/A neu berechnet.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->riskFieldSchema(), [
                'id' => ['type' => 'integer', 'description' => 'ID des Risikos (ERFORDERLICH).'],
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
            $risk = ManagementBookRisk::query()->where('team_id', $team->id)->find((int) ($arguments['id'] ?? 0));
            if (! $risk) {
                return ToolResult::error('NOT_FOUND', 'Risiko nicht gefunden.');
            }
            if ($denied = $this->authorizeRisk($context, 'update', $risk)) {
                return $denied;
            }
            if (array_key_exists('title', $arguments) && trim((string) $arguments['title']) === '') {
                return ToolResult::error('VALIDATION_ERROR', 'title darf nicht leer sein.');
            }
            if (array_key_exists('status', $arguments) && empty($arguments['status'])) {
                return ToolResult::error('VALIDATION_ERROR', 'status darf nicht leer sein.');
            }
            if ($message = $this->validateRiskInput($arguments, $risk)) {
                return ToolResult::error('VALIDATION_ERROR', $message);
            }

            $fields = $this->riskFields($arguments);
            if ($fields !== []) {
                $risk->update($fields);
            }

            return ToolResult::success([
                'risk' => $this->riskToArray($risk->fresh()),
                'message' => "Risiko #{$risk->id} aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->riskMetadata('PUT');
    }
}
