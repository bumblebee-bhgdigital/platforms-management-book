<?php

namespace Platform\ManagementBook\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Models\ManagementBookRisk;
use Platform\ManagementBook\Tools\Concerns\HandlesRisks;

class CreateRiskTool implements ToolContract, ToolMetadataContract
{
    use HandlesRisks;

    public function getName(): string
    {
        return 'management-book.risks.POST';
    }

    public function getDescription(): string
    {
        return 'POST /management-book/risks - Legt ein Risiko (Zeile der Risikomatrix) an einem Management Book an. Pflicht: management_book_id, title. '
            . 'Optional: number, description, organization_entity_id (Bereich/Abteilung), probability (E 1–5), impact (A 1–5), category, '
            . 'measures_planned, measures_implemented, owner_user_id|owner_agent_id, due_date, status (Default open), review_date. '
            . 'Score = E × A und die Ampel werden automatisch berechnet.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->riskFieldSchema(), [
                'management_book_id' => ['type' => 'integer', 'description' => 'Management Book, zu dem das Risiko gehört (ERFORDERLICH).'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ]),
            'required' => ['management_book_id', 'title'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            if (empty($arguments['management_book_id']) || trim((string) ($arguments['title'] ?? '')) === '') {
                return ToolResult::error('VALIDATION_ERROR', 'management_book_id und title sind erforderlich.');
            }
            $book = ManagementBook::query()->where('team_id', $team->id)->find((int) $arguments['management_book_id']);
            if (! $book) {
                return ToolResult::error('NOT_FOUND', 'Management Book nicht gefunden.');
            }
            if (! Gate::forUser($context->user)->allows('update', $book)) {
                return ToolResult::error('ACCESS_DENIED', "Keine Berechtigung, Risiken an Management Book #{$book->id} anzulegen.");
            }
            if ($message = $this->validateRiskInput($arguments)) {
                return ToolResult::error('VALIDATION_ERROR', $message);
            }

            $risk = ManagementBookRisk::create(array_merge($this->riskFields($arguments), [
                'team_id' => $book->team_id,
                'management_book_id' => $book->id,
                'created_by' => $context->user->id,
            ]));

            return ToolResult::success([
                'risk' => $this->riskToArray($risk->fresh()),
                'message' => "Risiko '{$risk->title}' (#{$risk->id}) an Management Book #{$book->id} angelegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->riskMetadata('POST');
    }
}
