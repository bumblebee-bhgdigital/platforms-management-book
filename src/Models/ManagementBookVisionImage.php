<?php

namespace Platform\ManagementBook\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Contracts\HasDisplayName;
use Platform\ManagementBook\Models\Concerns\GeneratesUuid;

/**
 * @ai.description Zielbild (Vision Image) in einem Fokusraum: wie sieht der gewünschte Zustand aus? Spalte 1 des Fokusraums.
 *
 * @property-read string $uuid
 */
class ManagementBookVisionImage extends Model implements HasDisplayName
{
    use SoftDeletes, GeneratesUuid;

    protected $table = 'management_book_vision_images';

    protected $fillable = [
        'uuid',
        'team_id',
        'management_book_id',
        'focus_area_id',
        'title',
        'description',
        'central_question',
        'order',
        'created_by',
    ];

    protected $casts = [
        'uuid' => 'string',
        'order' => 'integer',
    ];

    protected static function booted(): void
    {
        // management_book_id (und team_id) werden aus dem Fokusraum abgeleitet — wie entity_id in der Organization.
        static::saving(function (self $model) {
            $focusArea = ManagementBookFocusArea::withTrashed()->find($model->focus_area_id);
            if (! $focusArea) {
                throw new \InvalidArgumentException("focus_area_id {$model->focus_area_id} nicht gefunden.");
            }
            $model->management_book_id = $focusArea->management_book_id;
            if (empty($model->team_id)) {
                $model->team_id = $focusArea->team_id;
            }
        });
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(ManagementBook::class, 'management_book_id');
    }

    public function focusArea(): BelongsTo
    {
        return $this->belongsTo(ManagementBookFocusArea::class, 'focus_area_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function getDisplayName(): ?string
    {
        return $this->title;
    }
}
