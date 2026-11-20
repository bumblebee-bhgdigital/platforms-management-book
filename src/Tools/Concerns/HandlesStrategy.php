<?php

namespace Platform\ManagementBook\Tools\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;
use Platform\ManagementBook\Models\ManagementBook;
use Platform\ManagementBook\Models\ManagementBookFocusArea;
use Platform\ManagementBook\Models\ManagementBookForecast;
use Platform\ManagementBook\Models\ManagementBookMilestone;
use Platform\ManagementBook\Models\ManagementBookObstacle;
use Platform\ManagementBook\Models\ManagementBookStrategicDocument;
use Platform\ManagementBook\Models\ManagementBookVisionImage;

/**
 * Gemeinsame Helfer der Strategie-Tools (strategy, forecasts, focus_areas, vision_images, obstacles, milestones):
 * Book-Auflösung mit Rechteprüfung, Sichtbarkeits-Subquery, Serialisierung, Metadaten.
 */
trait HandlesStrategy
{
    use HandlesManagementBooks;

    /**
     * Book im Team laden und Recht prüfen (view/update).
     *
     * @return array{book: ManagementBook|null, error: ToolResult|null}
     */
    protected function findBook(array $arguments, Team $team, ToolContext $context, string $ability): array
    {
        $bookId = (int) ($arguments['management_book_id'] ?? 0);
        if ($bookId <= 0) {
            return ['book' => null, 'error' => ToolResult::error('VALIDATION_ERROR', 'management_book_id ist erforderlich.')];
        }
        $book = ManagementBook::query()->where('team_id', $team->id)->find($bookId);
        if (! $book) {
            return ['book' => null, 'error' => ToolResult::error('NOT_FOUND', 'Management Book nicht gefunden.')];
        }
        if ($denied = $this->authorizeBook($context, $ability, $book)) {
            return ['book' => null, 'error' => $denied];
        }

        return ['book' => $book, 'error' => null];
    }

    protected function authorizeModel(ToolContext $context, string $ability, Model $model, string $label): ?ToolResult
    {
        return Gate::forUser($context->user)->allows($ability, $model)
            ? null
            : ToolResult::error('ACCESS_DENIED', "Keine Berechtigung ({$ability}) für {$label} #{$model->getKey()}.");
    }

    /** IDs der für den User sichtbaren Books im Team (Subquery). */
    protected function visibleBookIds(Team $team, ToolContext $context): Builder
    {
        return ManagementBook::query()->where('team_id', $team->id)->visibleTo($context->user)->select('id');
    }

    /** @return array{0:int,1:int} [limit, offset] */
    protected function pageArgs(array $arguments, int $default = 50, int $max = 200): array
    {
        return [min(max((int) ($arguments['limit'] ?? $default), 1), $max), max((int) ($arguments['offset'] ?? 0), 0)];
    }

    /** Fokusraum im Team laden (ohne Rechteprüfung). */
    protected function findFocusArea(int $id, Team $team): ?ManagementBookFocusArea
    {
        return ManagementBookFocusArea::query()->where('team_id', $team->id)->find($id);
    }

    protected function toolMetadata(string $resource, string $verb): array
    {
        $read = $verb === 'GET';

        return [
            'category' => $read ? 'query' : 'action',
            'tags' => ['management-book', 'strategy', $resource, strtolower($verb)],
            'read_only' => $read,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => $read ? 'safe' : ($verb === 'DELETE' ? 'destructive' : 'write'),
            'idempotent' => in_array($verb, ['GET', 'PUT'], true),
        ];
    }

    /** @return array<string,mixed> */
    protected function documentToArray(ManagementBookStrategicDocument $d, bool $withContent = true): array
    {
        return [
            'id' => $d->id,
            'uuid' => $d->uuid,
            'management_book_id' => $d->management_book_id,
            'type' => $d->type?->value,
            'title' => $d->title,
            'version' => $d->version,
            'is_active' => $d->is_active,
            'valid_from' => $d->valid_from?->toDateString(),
            'change_note' => $d->change_note,
            'content' => $withContent ? $d->content : null,
        ];
    }

    /** @return array<string,mixed> */
    protected function forecastToArray(ManagementBookForecast $f, bool $withContent = true): array
    {
        return [
            'id' => $f->id,
            'uuid' => $f->uuid,
            'team_id' => $f->team_id,
            'management_book_id' => $f->management_book_id,
            'title' => $f->title,
            'target_date' => $f->target_date?->toDateString(),
            'target_year' => $f->target_date?->year,
            'current_version_id' => $f->current_version_id,
            'current_version' => $f->currentVersion?->version,
            'content' => $withContent ? ($f->currentVersion?->content ?? $f->content) : null,
            'created_by' => $f->created_by,
            'created_at' => $f->created_at?->toIso8601String(),
            'updated_at' => $f->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    protected function focusAreaToArray(ManagementBookFocusArea $fa, bool $withContent = true): array
    {
        return [
            'id' => $fa->id,
            'uuid' => $fa->uuid,
            'team_id' => $fa->team_id,
            'management_book_id' => $fa->management_book_id,
            'forecast_id' => $fa->forecast_id,
            'title' => $fa->title,
            'description' => $fa->description,
            'content' => $withContent ? $fa->content : null,
            'central_question_vision_images' => $fa->central_question_vision_images,
            'central_question_obstacles' => $fa->central_question_obstacles,
            'central_question_milestones' => $fa->central_question_milestones,
            'order' => (int) $fa->order,
            'created_by' => $fa->created_by,
            'created_at' => $fa->created_at?->toIso8601String(),
            'updated_at' => $fa->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    protected function itemToArray(Model $m): array
    {
        $a = [
            'id' => $m->id,
            'uuid' => $m->uuid,
            'team_id' => $m->team_id,
            'management_book_id' => $m->management_book_id,
            'focus_area_id' => $m->focus_area_id,
            'title' => $m->title,
            'description' => $m->description,
            'central_question' => $m->central_question,
            'order' => (int) $m->order,
        ];
        if ($m instanceof ManagementBookMilestone) {
            $a += [
                'target_date' => $m->target_date?->toDateString(),
                'target_year' => $m->target_year !== null ? (int) $m->target_year : null,
                'target_quarter' => $m->target_quarter !== null ? (int) $m->target_quarter : null,
            ];
        }
        $a['created_by'] = $m->created_by;
        $a['created_at'] = $m->created_at?->toIso8601String();
        $a['updated_at'] = $m->updated_at?->toIso8601String();

        return $a;
    }

    /** Klassen der Fokusraum-Kinder je Art. */
    protected function itemClassFor(string $kind): string
    {
        return match ($kind) {
            'vision_image' => ManagementBookVisionImage::class,
            'obstacle' => ManagementBookObstacle::class,
            'milestone' => ManagementBookMilestone::class,
        };
    }
}
