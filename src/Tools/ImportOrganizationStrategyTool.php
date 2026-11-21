<?php

namespace Platform\ManagementBook\Tools;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Exceptions\ManagementBookException;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Services\OrganizationStrategyImporter;
use Platform\ManagementBook\Tools\Concerns\HandlesManagementBooks;

class ImportOrganizationStrategyTool implements ToolContract, ToolMetadataContract
{
    use HandlesManagementBooks;

    public function getName(): string
    {
        return 'management-book.import_organization_strategy.POST';
    }

    public function getDescription(): string
    {
        return 'POST /management-book/import/organization-strategy - NUR FÜR DEN EINMALIGEN UMZUG von Altdaten (der Strategie-Layer der Organisation ist zurückgebaut, die Tabellen bestehen noch). Übernimmt den Strategie-Layer der Organisation eines Knotens (Mission/Vision, Forecasts inkl. aller Versionen, '
            . 'Fokusräume auch ohne Forecast, Zielbilder, Hindernisse, Meilensteine, OKR-Beiträge) serverseitig 1:1 in ein Management Book. Pflicht: entity_id. '
            . 'Ohne management_book_id wird ein neues aktives Book am Knoten angelegt (Fehler, wenn dort schon eins aktiv ist). '
            . 'Mit management_book_id + focus_area_ids werden nur diese Fokusräume nachgetragen. Idempotent (übernimmt die UUIDs der Quelle, bereits Übernommenes wird übersprungen). '
            . 'dry_run=true zeigt nur, was passieren würde. Die Organisation wird nicht verändert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_id' => ['type' => 'integer', 'description' => 'Knoten (Carrier-Entity) der Organisation (ERFORDERLICH).'],
                'management_book_id' => ['type' => 'integer', 'description' => 'Optional: bestehendes Book als Ziel (für Nachträge).'],
                'focus_area_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Optional: nur diese Org-Fokusräume (samt Einträgen, Forecast und Beiträgen) übernehmen.'],
                'dry_run' => ['type' => 'boolean', 'description' => 'Optional: nur zählen, nichts speichern (Default false).'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ],
            'required' => ['entity_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            $entityId = (int) ($arguments['entity_id'] ?? 0);
            if ($entityId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_id ist erforderlich.');
            }
            if (! $this->canWriteEntity($context, $entityId)) {
                return ToolResult::error('ACCESS_DENIED', "Kein Schreibrecht auf Knoten {$entityId}.");
            }

            $book = null;
            if (! empty($arguments['management_book_id'])) {
                $book = ManagementBook::query()->where('team_id', $team->id)->find((int) $arguments['management_book_id']);
                if (! $book) {
                    return ToolResult::error('NOT_FOUND', 'Management Book nicht gefunden.');
                }
                if ($denied = $this->authorizeBook($context, 'update', $book)) {
                    return $denied;
                }
            } elseif (! Gate::forUser($context->user)->allows('create', ManagementBook::class)) {
                return ToolResult::error('ACCESS_DENIED', 'Du darfst keine Management Books anlegen.');
            }

            $only = isset($arguments['focus_area_ids']) && is_array($arguments['focus_area_ids'])
                ? array_values(array_map('intval', $arguments['focus_area_ids'])) : null;

            $report = app(OrganizationStrategyImporter::class)->import($entityId, $context->user, $book, $only, (bool) ($arguments['dry_run'] ?? false));

            return ToolResult::success($report + [
                'message' => ($report['dry_run'] ? 'Probelauf: ' : '') . "Strategie von {$report['entity']['name']} übernommen.",
            ]);
        } catch (ManagementBookException $e) {
            return ToolResult::error($e->errorCode, $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Import: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['management-book', 'import', 'organization', 'strategy', 'migration'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
