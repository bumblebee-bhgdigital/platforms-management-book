<?php

namespace Platform\ManagementBook\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Enums\MilestoneStatus;
use Platform\ManagementBook\Models\ManagementBookMilestone;
use Platform\ManagementBook\Tools\Concerns\HandlesTransformationMap;

class UpdateTransformationMapTool implements ToolContract, ToolMetadataContract
{
    use HandlesTransformationMap;

    private const FIELDS = ['status', 'order', 'target_year', 'target_quarter', 'target_date', 'focus_area_id'];

    public function getName(): string
    {
        return 'management-book.transformation_map.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /management-book/books/{id}/transformation-map - Ändert mehrere Waypoints (Meilensteine) der Transformation Map in einem Schritt: Status, Reihenfolge (order), '
            . 'Zeitpunkt (target_year/target_quarter/target_date) oder Fokusraum. Pflicht: management_book_id und milestones (Liste von {id, …}, max. 200). '
            . 'Alles-oder-nichts: ist ein Eintrag ungültig, wird nichts geändert. Einzeländerungen (Titel etc.) über management-book.milestones.PUT.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'management_book_id' => ['type' => 'integer', 'description' => 'Management Book (ERFORDERLICH).'],
                'milestones' => [
                    'type' => 'array',
                    'description' => 'Liste der zu ändernden Waypoints (ERFORDERLICH).',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer', 'description' => 'Meilenstein-ID (ERFORDERLICH).'],
                            'status' => ['type' => 'string', 'enum' => MilestoneStatus::values(), 'description' => 'Neuer Status.'],
                            'order' => ['type' => 'integer', 'description' => 'Neue Reihenfolge.'],
                            'target_year' => ['type' => 'integer', 'description' => 'Neues Zieljahr.'],
                            'target_quarter' => ['type' => 'integer', 'description' => 'Neues Zielquartal 1-4.'],
                            'target_date' => ['type' => 'string', 'description' => 'Neues Zieldatum YYYY-MM-DD.'],
                            'focus_area_id' => ['type' => 'integer', 'description' => 'In anderen Fokusraum desselben Books verschieben.'],
                        ],
                        'required' => ['id'],
                    ],
                ],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ],
            'required' => ['management_book_id', 'milestones'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            ['book' => $book, 'error' => $error] = $this->findBook($arguments, $team, $context, 'update');
            if ($error) {
                return $error;
            }
            $items = $arguments['milestones'] ?? null;
            if (! is_array($items) || $items === []) {
                return ToolResult::error('VALIDATION_ERROR', 'milestones muss eine nicht-leere Liste sein.');
            }
            if (count($items) > 200) {
                return ToolResult::error('VALIDATION_ERROR', 'Maximal 200 Waypoints je Aufruf.');
            }

            // Erst alles prüfen, dann in einer Transaktion schreiben (alles oder nichts).
            $plan = [];
            foreach ($items as $idx => $item) {
                $id = (int) (is_array($item) ? ($item['id'] ?? 0) : 0);
                if ($id <= 0) {
                    return ToolResult::error('VALIDATION_ERROR', "milestones[{$idx}].id ist erforderlich.");
                }
                $milestone = ManagementBookMilestone::query()->where('management_book_id', $book->id)->find($id);
                if (! $milestone) {
                    return ToolResult::error('NOT_FOUND', "Meilenstein #{$id} gehört nicht zu Management Book #{$book->id}.");
                }
                if ($message = $this->validateItemInput('milestone', $item)) {
                    return ToolResult::error('VALIDATION_ERROR', "milestones[{$idx}]: {$message}");
                }
                $fields = array_intersect_key($item, array_flip(self::FIELDS));
                if (isset($fields['focus_area_id'])) {
                    $target = $this->findFocusArea((int) $fields['focus_area_id'], $team);
                    if (! $target || (int) $target->management_book_id !== (int) $book->id) {
                        return ToolResult::error('VALIDATION_ERROR', "milestones[{$idx}]: Ziel-Fokusraum nicht gefunden oder in anderem Book.");
                    }
                    $fields['focus_area_id'] = $target->id;
                }
                if ($fields === []) {
                    return ToolResult::error('VALIDATION_ERROR', "milestones[{$idx}]: keine änderbaren Felder angegeben (" . implode(', ', self::FIELDS) . ').');
                }
                $plan[] = [$milestone, $fields];
            }

            DB::transaction(function () use ($plan) {
                foreach ($plan as [$milestone, $fields]) {
                    $milestone->update($fields);
                }
            });

            return ToolResult::success([
                'management_book_id' => $book->id,
                'updated' => count($plan),
                'milestones' => array_map(fn ($p) => $this->itemToArray($p[0]->fresh()), $plan),
                'message' => count($plan) . ' Waypoint(s) der Transformation Map aktualisiert.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return ToolResult::error('VALIDATION_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Transformation Map: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return $this->toolMetadata('transformation_map', 'PUT');
    }
}
