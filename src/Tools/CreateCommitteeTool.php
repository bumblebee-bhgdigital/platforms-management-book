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
        return 'POST /management-book/committees - Legt ein Gremium an einem Management Book an. Pflicht: management_book_id, name. '
            . 'type (Default other), status (Default active), cadence (Freitext), chair_user_id, responsible_agent_id, signal_set_id, members[] optional. '
            . 'Das Tool enthält keine Ablauflogik: Agenda, Regeln und Ablauf legt der Agent selbst fest.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->committeeFieldSchema(), [
                'management_book_id' => ['type' => 'integer', 'description' => 'Management Book, zu dem das Gremium gehört (ERFORDERLICH).'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ]),
            'required' => ['management_book_id', 'name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            if (empty($arguments['management_book_id']) || trim((string) ($arguments['name'] ?? '')) === '') {
                return ToolResult::error('VALIDATION_ERROR', 'management_book_id und name sind erforderlich.');
            }
            $book = ManagementBook::query()->where('team_id', $team->id)->find((int) $arguments['management_book_id']);
            if (! $book) {
                return ToolResult::error('NOT_FOUND', 'Management Book nicht gefunden.');
            }
            if (! Gate::forUser($context->user)->allows('update', $book)) {
                return ToolResult::error('ACCESS_DENIED', "Keine Berechtigung, Gremien an Management Book #{$book->id} anzulegen.");
            }
            if ($message = $this->validateCommitteeInput($arguments, $team->id)) {
                return ToolResult::error('VALIDATION_ERROR', $message);
            }

            $committee = DB::transaction(function () use ($arguments, $team, $book, $context) {
                $committee = ManagementBookCommittee::create([
                    'team_id' => $team->id,
                    'management_book_id' => $book->id,
                    'name' => trim($arguments['name']),
                    'type' => $arguments['type'] ?? null,
                    'description' => $arguments['description'] ?? null,
                    'cadence' => $arguments['cadence'] ?? null,
                    'chair_user_id' => $arguments['chair_user_id'] ?? null,
                    'responsible_agent_id' => $arguments['responsible_agent_id'] ?? null,
                    'signal_set_id' => $arguments['signal_set_id'] ?? null,
                    'status' => $arguments['status'] ?? null,
                    'created_by' => $context->user->id,
                ]);
                $this->syncMembers($committee, $arguments['members'] ?? []);

                return $committee;
            });

            return ToolResult::success([
                'committee' => $this->committeeToArray($committee->fresh()),
                'message' => "Gremium '{$committee->name}' (#{$committee->id}) an Management Book #{$book->id} angelegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['management-book', 'committees', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
