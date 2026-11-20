<?php

namespace Platform\ManagementBook\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Enums\ManagementBookStatus;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Services\ManagementBookService;
use Platform\ManagementBook\Tools\Concerns\HandlesManagementBooks;

class ListManagementBooksTool implements ToolContract, ToolMetadataContract
{
    use HandlesManagementBooks;

    public function getName(): string
    {
        return 'management-book.books.GET';
    }

    public function getDescription(): string
    {
        return 'GET /management-books - Listet Management Books des Teams (nur für den User sichtbare). Einzelnes Book: id=<book_id>. '
            . '"Aktives Book eines Knotens": entity_id=<Entity-ID> + active_only=true (liefert das eine aktive Book oder active_book=null). '
            . 'Ohne active_only: alle Books an diesem Knoten (Entwurf/aktiv/abgelöst/archiviert). Weitere Filter: status, unlinked=true (Books ohne Knoten), search, limit/offset.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'Optional: einzelnes Book anhand der ID.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
                'entity_id' => ['type' => 'integer', 'description' => 'Optional: nur Books, die an diesem Knoten (Organisations-Entity) hängen.'],
                'active_only' => ['type' => 'boolean', 'description' => 'Nur mit entity_id sinnvoll: liefert nur das aktive Book des Knotens (Feld active_book).'],
                'status' => ['type' => 'string', 'enum' => ManagementBookStatus::values(), 'description' => 'Optional: Status-Filter.'],
                'unlinked' => ['type' => 'boolean', 'description' => 'Optional: nur Books ohne Knoten-Verknüpfung.'],
                'search' => ['type' => 'string', 'description' => 'Optional: Suche in Titel/Beschreibung.'],
                'limit' => ['type' => 'integer', 'description' => 'Optional: max. Anzahl (Default 50, max 200).'],
                'offset' => ['type' => 'integer', 'description' => 'Optional: Offset.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            $service = app(ManagementBookService::class);

            if (! empty($arguments['id'])) {
                $book = ManagementBook::query()->where('team_id', $team->id)->find((int) $arguments['id']);
                if (! $book) {
                    return ToolResult::error('NOT_FOUND', 'Management Book nicht gefunden.');
                }
                if ($denied = $this->authorizeBook($context, 'view', $book)) {
                    return $denied;
                }

                return ToolResult::success(['book' => $this->bookToArray($book)]);
            }

            $query = ManagementBook::query()->where('team_id', $team->id)->visibleTo($context->user);

            if (! empty($arguments['status'])) {
                $status = ManagementBookStatus::tryFrom((string) $arguments['status']);
                if (! $status) {
                    return ToolResult::error('VALIDATION_ERROR', 'status muss einer von ' . implode(', ', ManagementBookStatus::values()) . ' sein.');
                }
                $query->status($status);
            }

            if (! empty($arguments['entity_id'])) {
                $ids = $service->booksForNode((int) $arguments['entity_id'])->pluck('id')->all();
                $query->whereIn('id', $ids ?: [0]);
                if (! empty($arguments['active_only'])) {
                    $query->status(ManagementBookStatus::ACTIVE);
                }
            }

            if (! empty($arguments['search'])) {
                $term = '%' . $arguments['search'] . '%';
                $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('description', 'like', $term));
            }

            $books = $query->orderBy('title')->get();

            if (! empty($arguments['unlinked'])) {
                $linked = \Platform\Organization\Services\EntityDimensionBridge::linksForLinkables(
                    [ManagementBook::MORPH_ALIAS], $books->pluck('id')->all(), false
                )->pluck('linkable_id')->all();
                $books = $books->reject(fn ($b) => in_array($b->id, $linked, true))->values();
            }

            $limit = min(max((int) ($arguments['limit'] ?? 50), 1), 200);
            $offset = max((int) ($arguments['offset'] ?? 0), 0);
            $total = $books->count();
            $page = $books->slice($offset, $limit)->values();

            $payload = [
                'books' => $page->map(fn ($b) => $this->bookToArray($b))->all(),
                'total' => $total,
                'team_id' => $team->id,
            ];
            if (! empty($arguments['entity_id']) && ! empty($arguments['active_only'])) {
                $active = $page->first();
                $payload['entity_id'] = (int) $arguments['entity_id'];
                $payload['active_book'] = $active ? $this->bookToArray($active) : null;
            }

            return ToolResult::success($payload);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Management Books: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['management-book', 'books', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
