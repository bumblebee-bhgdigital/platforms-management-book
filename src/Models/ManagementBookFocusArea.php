<?php

namespace Platform\ManagementBook\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Contracts\HasDisplayName;
use Platform\ManagementBook\Models\Concerns\GeneratesUuid;

/**
 * @ai.description Fokusraum (strategisches Handlungsfeld, empfohlen 5–7 je Management Book) mit drei Spalten: Zielbilder, Hindernisse, Meilensteine – je mit einer Leitfrage.
 *
 * @property-read string $uuid
 */
class ManagementBookFocusArea extends Model implements HasDisplayName
{
    use SoftDeletes, GeneratesUuid;

    /** Empfohlene Obergrenze je Book (nur Hinweis, keine harte Grenze). */
    public const RECOMMENDED_MAX = 7;

    protected $table = 'management_book_focus_areas';

    protected $fillable = [
        'uuid',
        'team_id',
        'management_book_id',
        'forecast_id',
        'title',
        'description',
        'content',
        'central_question_vision_images',
        'central_question_obstacles',
        'central_question_milestones',
        'order',
        'created_by',
    ];

    protected $casts = [
        'uuid' => 'string',
        'order' => 'integer',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(ManagementBook::class, 'management_book_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(ManagementBookForecast::class, 'forecast_id');
    }

    public function visionImages(): HasMany
    {
        return $this->hasMany(ManagementBookVisionImage::class, 'focus_area_id')->orderBy('order');
    }

    public function obstacles(): HasMany
    {
        return $this->hasMany(ManagementBookObstacle::class, 'focus_area_id')->orderBy('order');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ManagementBookMilestone::class, 'focus_area_id')->orderBy('order');
    }

    public function getDisplayName(): ?string
    {
        return $this->title;
    }
}
