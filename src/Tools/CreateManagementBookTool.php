<?php

namespace Platform\ManagementBook\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Exceptions\ManagementBookException;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Services\ManagementBookService;
use Platform\ManagementBook\Tools\Concerns\HandlesManagementBooks;

class CreateManagementBookTool implements ToolContract, ToolMetadataContract
{
    use HandlesManagementBooks;

    public function getName(): string
    {
        return 'management-book.books.POST';
    }

    public function getDescription(): string
    {
        return 'POST /management-books - Legt ein Management Book an (Status standardmäßig draft). Pflicht: title. '
            . 'Optional entity_id: hängt das Book direkt an einen Knoten. status=active aktiviert es sofort und löst ein bisher aktives Book desselben Knotens ab. '
            . 'Neue Strategieperioden sind neue Books, keine Versionen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->bookFieldSchema(), [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
                'entity_id' => ['type' => 'integer', 'description' => 'Optional: Knoten (Organisations-Entity), an den das Book gehängt wird. Nutze "organization.entities.GET".'],
                'status' => ['type' => 'string', 'enum' => ['draft', 'active'], 'description' => 'Optional: draft (Default) oder active.'],
            ]),
            'required' => ['title'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            if (! Gate::forUser($context->user)->allows('create', ManagementBook::class)) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst keine Management Books anlegen.');
            }

            $entityId = ! empty($arguments['entity_id']) ? (int) $arguments['entity_id'] : null;
            if ($entityId && ! $this->canWriteEntity($context, $entityId)) {
                return ToolResult::error('ACCESS_DENIED', "Kein Schreibrecht auf Knoten {$entityId} (oder Knoten existiert nicht).");
            }

            $book = app(ManagementBookService::class)->create($context->user, $team, $arguments, $entityId);

            return ToolResult::success([
                'book' => $this->bookToArray($book),
                'message' => "Management Book '{$book->title}' (#{$book->id}) angelegt (Status {$book->status->value}).",
            ]);
        } catch (ManagementBookException $e) {
            return ToolResult::error($e->errorCode, $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['management-book', 'books', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
