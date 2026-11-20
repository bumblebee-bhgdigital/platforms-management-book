<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Services\ManagementBookService;
use Platform\ManagementBook\Tools\Concerns\HandlesManagementBooks;

class DeleteManagementBookTool implements ToolContract, ToolMetadataContract
{
    use HandlesManagementBooks;

    public function getName(): string
    {
        return 'management-book.books.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /management-books/{id} - Löscht ein Management Book (Soft-Delete, wiederherstellbar). Pflicht: id. '
            . 'Zum Beenden eines Books ohne Löschen stattdessen management-book.books.PUT mit status=archived nutzen (Historie bleibt sichtbar). '
            . 'Ein gelöschtes aktives Book gibt den Knoten frei (es gibt dann kein aktives Book mehr am Knoten).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID des Books (ERFORDERLICH).'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ],
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
            $book = ManagementBook::query()->where('team_id', $team->id)->find((int) ($arguments['id'] ?? 0));
            if (! $book) {
                return ToolResult::error('NOT_FOUND', 'Management Book nicht gefunden.');
            }
            if ($denied = $this->authorizeBook($context, 'delete', $book)) {
                return $denied;
            }

            $title = $book->title;
            $id = $book->id;
            app(ManagementBookService::class)->delete($book);

            return ToolResult::success([
                'id' => $id,
                'title' => $title,
                'message' => "Management Book '{$title}' (#{$id}) gelöscht (Soft-Delete).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['management-book', 'books', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'destructive',
            'idempotent' => false,
        ];
    }
}
