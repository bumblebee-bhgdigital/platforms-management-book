<?php

namespace Platform\ManagementBook\Tools\Concerns;

use Illuminate\Support\Carbon;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Enums\MilestoneStatus;

/**
 * CRUD-Logik für die drei Spalten eines Fokusraums (Zielbilder = vision_image, Hindernisse = obstacle,
 * Meilensteine = milestone). Jede Tool-Klasse liefert nur Name/Beschreibung und ruft run*() mit ihrer Art auf.
 */
trait HandlesFocusAreaItems
{
    use HandlesStrategy;

    /** @return array{label:string, plural:string, fields:string[]} */
    protected function itemConfig(string $kind): array
    {
        $base = ['title', 'description', 'central_question', 'order'];

        return match ($kind) {
            'vision_image' => ['label' => 'Zielbild', 'plural' => 'vision_images', 'fields' => $base],
            'obstacle' => ['label' => 'Hindernis', 'plural' => 'obstacles', 'fields' => $base],
            'milestone' => ['label' => 'Meilenstein', 'plural' => 'milestones', 'fields' => array_merge($base, ['target_date', 'target_year', 'target_quarter', 'status'])],
        };
    }

    /** Property-Definitionen für POST/PUT. */
    protected function itemFieldSchema(string $kind): array
    {
        $props = [
            'title' => ['type' => 'string', 'description' => 'Titel.'],
            'description' => ['type' => 'string', 'description' => 'Beschreibung.'],
            'central_question' => ['type' => 'string', 'description' => 'Leitfrage.'],
            'order' => ['type' => 'integer', 'description' => 'Reihenfolge innerhalb der Spalte (Default 0).'],
        ];
        if ($kind === 'milestone') {
            $props += [
                'target_date' => ['type' => 'string', 'description' => 'Optional: exaktes Datum YYYY-MM-DD.'],
                'target_year' => ['type' => 'integer', 'description' => 'Zieljahr für die Positionierung auf der Transformation Map.'],
                'target_quarter' => ['type' => 'integer', 'description' => 'Zielquartal 1-4.'],
                'status' => ['type' => 'string', 'enum' => MilestoneStatus::values(), 'description' => 'Status des Waypoints: planned (geplant, Default), in_progress, reached (erreicht; setzt reached_at), missed (verfehlt), cancelled (verworfen).'],
            ];
        }

        return $props;
    }

    protected function itemListSchema(string $kind): array
    {
        $props = [
            'id' => ['type' => 'integer', 'description' => 'Optional: einzelnes Element anhand der ID.'],
            'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            'management_book_id' => ['type' => 'integer', 'description' => 'Optional: nur Elemente dieses Management Books.'],
            'focus_area_id' => ['type' => 'integer', 'description' => 'Optional: nur Elemente dieses Fokusraums.'],
            'search' => ['type' => 'string', 'description' => 'Optional: Suche in Titel/Beschreibung.'],
            'limit' => ['type' => 'integer', 'description' => 'Optional: max. Anzahl (Default 50, max 200).'],
            'offset' => ['type' => 'integer', 'description' => 'Optional: Offset.'],
        ];
        if ($kind === 'milestone') {
            $props += [
                'target_year' => ['type' => 'integer', 'description' => 'Optional: Zieljahr filtern.'],
                'target_quarter' => ['type' => 'integer', 'description' => 'Optional: Zielquartal (1-4) filtern.'],
                'status' => ['type' => 'string', 'enum' => MilestoneStatus::values(), 'description' => 'Optional: Status filtern.'],
            ];
        }

        return ['type' => 'object', 'properties' => $props];
    }

    protected function validateItemInput(string $kind, array $arguments): ?string
    {
        if ($kind !== 'milestone') {
            return null;
        }
        if (isset($arguments['target_quarter']) && ((int) $arguments['target_quarter'] < 1 || (int) $arguments['target_quarter'] > 4)) {
            return 'target_quarter muss zwischen 1 und 4 liegen.';
        }
        if (isset($arguments['status']) && ! MilestoneStatus::tryFrom((string) $arguments['status'])) {
            return 'status muss einer von ' . implode(', ', MilestoneStatus::values()) . ' sein.';
        }
        if (isset($arguments['target_year']) && ((int) $arguments['target_year'] < 1900 || (int) $arguments['target_year'] > 2200)) {
            return 'target_year ist ungültig.';
        }
        if (! empty($arguments['target_date'])) {
            try {
                Carbon::parse((string) $arguments['target_date']);
            } catch (\Throwable $e) {
                return 'target_date ist kein gültiges Datum (YYYY-MM-DD).';
            }
        }

        return null;
    }

    protected function runItemList(string $kind, array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            $cfg = $this->itemConfig($kind);
            $class = $this->itemClassFor($kind);

            if (! empty($arguments['id'])) {
                $item = $class::query()->where('team_id', $team->id)->find((int) $arguments['id']);
                if (! $item) {
                    return ToolResult::error('NOT_FOUND', "{$cfg['label']} nicht gefunden.");
                }
                if ($denied = $this->authorizeModel($context, 'view', $item, $cfg['label'])) {
                    return $denied;
                }

                return ToolResult::success([$kind => $this->itemToArray($item)]);
            }

            $query = $class::query()->where('team_id', $team->id)->whereIn('management_book_id', $this->visibleBookIds($team, $context));
            if (! empty($arguments['management_book_id'])) {
                $query->where('management_book_id', (int) $arguments['management_book_id']);
            }
            if (! empty($arguments['focus_area_id'])) {
                $query->where('focus_area_id', (int) $arguments['focus_area_id']);
            }
            if ($kind === 'milestone') {
                foreach (['target_year', 'target_quarter'] as $col) {
                    if (! empty($arguments[$col])) {
                        $query->where($col, (int) $arguments[$col]);
                    }
                }
                if (! empty($arguments['status'])) {
                    if (! MilestoneStatus::tryFrom((string) $arguments['status'])) {
                        return ToolResult::error('VALIDATION_ERROR', 'status muss einer von ' . implode(', ', MilestoneStatus::values()) . ' sein.');
                    }
                    $query->where('status', $arguments['status']);
                }
            }
            if (! empty($arguments['search'])) {
                $term = '%' . $arguments['search'] . '%';
                $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('description', 'like', $term));
            }

            $total = (clone $query)->count();
            [$limit, $offset] = $this->pageArgs($arguments);
            if ($kind === 'milestone') {
                $query->orderBy('target_year')->orderBy('target_quarter')->orderBy('order');
            } else {
                $query->orderBy('focus_area_id')->orderBy('order');
            }
            $rows = $query->orderBy('id')->skip($offset)->take($limit)->get();

            return ToolResult::success([
                $cfg['plural'] => $rows->map(fn ($m) => $this->itemToArray($m))->all(),
                'total' => $total,
                'team_id' => $team->id,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden: ' . $e->getMessage());
        }
    }

    protected function runItemCreate(string $kind, array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            $cfg = $this->itemConfig($kind);
            if (empty($arguments['focus_area_id']) || trim((string) ($arguments['title'] ?? '')) === '') {
                return ToolResult::error('VALIDATION_ERROR', 'focus_area_id und title sind erforderlich.');
            }
            $focusArea = $this->findFocusArea((int) $arguments['focus_area_id'], $team);
            if (! $focusArea) {
                return ToolResult::error('NOT_FOUND', 'Fokusraum nicht gefunden.');
            }
            if ($denied = $this->authorizeModel($context, 'update', $focusArea, 'Fokusraum')) {
                return $denied;
            }
            if ($message = $this->validateItemInput($kind, $arguments)) {
                return ToolResult::error('VALIDATION_ERROR', $message);
            }

            $data = array_intersect_key($arguments, array_flip($cfg['fields']));
            $data['title'] = trim($arguments['title']);
            $data['order'] = (int) ($data['order'] ?? 0);
            $data['focus_area_id'] = $focusArea->id;
            $data['team_id'] = $team->id;
            $data['created_by'] = $context->user->id;

            $class = $this->itemClassFor($kind);
            $item = $class::create($data);

            return ToolResult::success([
                $kind => $this->itemToArray($item->fresh()),
                'message' => "{$cfg['label']} '{$item->title}' (#{$item->id}) im Fokusraum #{$focusArea->id} angelegt.",
            ]);
        } catch (\InvalidArgumentException $e) {
            return ToolResult::error('VALIDATION_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen: ' . $e->getMessage());
        }
    }

    protected function runItemUpdate(string $kind, array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            $cfg = $this->itemConfig($kind);
            if (empty($arguments['id'])) {
                return ToolResult::error('VALIDATION_ERROR', 'id ist erforderlich.');
            }
            $class = $this->itemClassFor($kind);
            $item = $class::query()->where('team_id', $team->id)->find((int) $arguments['id']);
            if (! $item) {
                return ToolResult::error('NOT_FOUND', "{$cfg['label']} nicht gefunden.");
            }
            if ($denied = $this->authorizeModel($context, 'update', $item, $cfg['label'])) {
                return $denied;
            }
            if (array_key_exists('title', $arguments) && trim((string) $arguments['title']) === '') {
                return ToolResult::error('VALIDATION_ERROR', 'title darf nicht leer sein.');
            }
            if ($message = $this->validateItemInput($kind, $arguments)) {
                return ToolResult::error('VALIDATION_ERROR', $message);
            }

            $fields = array_intersect_key($arguments, array_flip($cfg['fields']));
            if (isset($fields['title'])) {
                $fields['title'] = trim($fields['title']);
            }
            // Verschieben in einen anderen Fokusraum desselben Books
            if (! empty($arguments['focus_area_id']) && (int) $arguments['focus_area_id'] !== (int) $item->focus_area_id) {
                $target = $this->findFocusArea((int) $arguments['focus_area_id'], $team);
                if (! $target || (int) $target->management_book_id !== (int) $item->management_book_id) {
                    return ToolResult::error('VALIDATION_ERROR', 'Ziel-Fokusraum nicht gefunden oder gehört zu einem anderen Management Book.');
                }
                $fields['focus_area_id'] = $target->id;
            }
            if ($fields !== []) {
                $item->update($fields);
            }

            return ToolResult::success([
                $kind => $this->itemToArray($item->fresh()),
                'message' => "{$cfg['label']} #{$item->id} aktualisiert.",
            ]);
        } catch (\InvalidArgumentException $e) {
            return ToolResult::error('VALIDATION_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    protected function runItemDelete(string $kind, array $arguments, ToolContext $context): ToolResult
    {
        try {
            ['team' => $team, 'error' => $error] = $this->resolveTeam($arguments, $context);
            if ($error) {
                return $error;
            }
            $cfg = $this->itemConfig($kind);
            $class = $this->itemClassFor($kind);
            $item = $class::query()->where('team_id', $team->id)->find((int) ($arguments['id'] ?? 0));
            if (! $item) {
                return ToolResult::error('NOT_FOUND', "{$cfg['label']} nicht gefunden.");
            }
            if ($denied = $this->authorizeModel($context, 'delete', $item, $cfg['label'])) {
                return $denied;
            }

            $id = $item->id;
            $title = $item->title;
            $item->delete();

            return ToolResult::success([
                'id' => $id,
                'title' => $title,
                'message' => "{$cfg['label']} '{$title}' (#{$id}) gelöscht (Soft-Delete).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen: ' . $e->getMessage());
        }
    }

    /** Schema für POST (focus_area_id + title Pflicht). */
    protected function itemCreateSchema(string $kind): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->itemFieldSchema($kind), [
                'focus_area_id' => ['type' => 'integer', 'description' => 'Fokusraum (ERFORDERLICH).'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ]),
            'required' => ['focus_area_id', 'title'],
        ];
    }

    protected function itemUpdateSchema(string $kind): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->itemFieldSchema($kind), [
                'id' => ['type' => 'integer', 'description' => 'ID (ERFORDERLICH).'],
                'focus_area_id' => ['type' => 'integer', 'description' => 'Optional: in einen anderen Fokusraum desselben Books verschieben.'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ]),
            'required' => ['id'],
        ];
    }

    protected function itemDeleteSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID (ERFORDERLICH).'],
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID (Default: aktuelles Team).'],
            ],
            'required' => ['id'],
        ];
    }
}
