<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Enums\ManagementBookStatus;
use Platform\ManagementBook\Exceptions\ManagementBookException;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Services\ManagementBookService;
use Platform\ManagementBook\Tools\Concerns\HandlesManagementBooks;

class UpdateManagementBookTool implements ToolContract, ToolMetadataContract
{
    use HandlesManagementBooks;

    public function getName(): string
    {
        return 'management-book.books.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /management-books/{id} - Ändert ein Management Book. Pflicht: id. '
            . 'Felder ändern (title, description, valid_from/until, horizon_year, owner_user_id, responsible_agent_user_id, review_rhythm, next_review_at). '
            . 'status=active aktiviert das Book und löst das bisher aktive Book desselben Knotens automatisch ab (superseded); status=archived archiviert. '
            . 'Knoten: entity_id hängt das Book (um) an einen Knoten, unlink=true löst die Verknüpfung (Book wird ungeknotet).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->bookFieldSchema(), [
                'id' => ['type' => 'integer', 'description' => 'ID des Books (ERFORDERLICH).'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
                'status' => ['type' => 'string', 'enum' => ['active', 'archived'], 'description' => 'Optional: active = aktivieren/ablösen, archived = archivieren.'],
                'entity_id' => ['type' => 'integer', 'description' => 'Optional: Book an diesen Knoten umhängen (ersetzt bisherige Knoten).'],
                'unlink' => ['type' => 'boolean', 'description' => 'Optional: Knoten-Verknüpfung(en) lösen.'],
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

            $book = ManagementBook::query()->where('team_id', $team->id)->find((int) $arguments['id']);
            if (! $book) {
                return ToolResult::error('NOT_FOUND', 'Management Book nicht gefunden.');
            }
            if ($denied = $this->authorizeBook($context, 'update', $book)) {
                return $denied;
            }

            $status = null;
            if (! empty($arguments['status'])) {
                $status = ManagementBookStatus::tryFrom((string) $arguments['status']);
                if (! in_array($status, [ManagementBookStatus::ACTIVE, ManagementBookStatus::ARCHIVED], true)) {
                    return ToolResult::error('VALIDATION_ERROR', "status darf nur 'active' oder 'archived' sein (abgelöst wird automatisch beim Aktivieren eines anderen Books).");
                }
            }

            $entityId = ! empty($arguments['entity_id']) ? (int) $arguments['entity_id'] : null;
            if ($entityId && ! $this->canWriteEntity($context, $entityId)) {
                return ToolResult::error('ACCESS_DENIED', "Kein Schreibrecht auf Knoten {$entityId} (oder Knoten existiert nicht).");
            }

            $service = app(ManagementBookService::class);
            $superseded = [];

            $fields = array_intersect_key($arguments, array_flip(['title', 'description', 'valid_from', 'valid_until', 'horizon_year', 'owner_user_id', 'responsible_agent_user_id', 'review_rhythm', 'next_review_at']));
            if ($fields !== []) {
                $book = $service->update($book, $fields);
            }
            if (! empty($arguments['unlink'])) {
                $service->unlink($book);
            }
            if ($entityId) {
                $service->relink($book, $entityId);
            }
            if ($status === ManagementBookStatus::ACTIVE) {
                $superseded = $service->activate($book)['superseded'];
            } elseif ($status === ManagementBookStatus::ARCHIVED) {
                $service->archive($book);
            }

            $book->refresh();

            return ToolResult::success([
                'book' => $this->bookToArray($book),
                'superseded' => array_map(fn ($b) => ['id' => $b->id, 'title' => $b->title], $superseded),
                'message' => "Management Book #{$book->id} aktualisiert (Status {$book->status->value})."
                    . ($superseded ? ' Abgelöst: ' . implode(', ', array_map(fn ($b) => "#{$b->id}", $superseded)) . '.' : ''),
            ]);
        } catch (ManagementBookException $e) {
            return ToolResult::error($e->errorCode, $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['management-book', 'books', 'update', 'activate', 'archive', 'link'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
