<?php

namespace Platform\ManagementBook\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Core\Contracts\HasDisplayName;
use Platform\ManagementBook\Enums\MilestoneStatus;
use Platform\ManagementBook\Models\Concerns\GeneratesUuid;

/**
 * @ai.description Meilenstein in einem Fokusraum (Spalte 3) und Waypoint auf der Transformation Map. Primäre Achsen: target_year + target_quarter, target_date optional.
 *
 * @property-read string $uuid
 */
class ManagementBookMilestone extends Model implements HasDisplayName
{
    use SoftDeletes, GeneratesUuid;

    protected $table = 'management_book_milestones';

    protected $fillable = [
        'uuid',
        'team_id',
        'management_book_id',
        'focus_area_id',
        'title',
        'description',
        'central_question',
        'target_date',
        'target_year',
        'target_quarter',
        'status',
        'reached_at',
        'order',
        'created_by',
    ];

    protected $casts = [
        'uuid' => 'string',
        'target_date' => 'date',
        'target_year' => 'integer',
        'target_quarter' => 'integer',
        'status' => MilestoneStatus::class,
        'reached_at' => 'date',
        'order' => 'integer',
    ];

    protected static function booted(): void
    {
        // management_book_id (und team_id) werden aus dem Fokusraum abgeleitet — wie entity_id in der Organization.
        static::creating(function (self $model) {
            if (empty($model->status)) {
                $model->status = MilestoneStatus::PLANNED;
            }
        });

        static::saving(function (self $model) {
            $focusArea = ManagementBookFocusArea::withTrashed()->find($model->focus_area_id);
            if (! $focusArea) {
                throw new \InvalidArgumentException("focus_area_id {$model->focus_area_id} nicht gefunden.");
            }
            $model->management_book_id = $focusArea->management_book_id;
            if (empty($model->team_id)) {
                $model->team_id = $focusArea->team_id;
            }

            if ($model->target_quarter !== null && ($model->target_quarter < 1 || $model->target_quarter > 4)) {
                throw new \InvalidArgumentException("target_quarter muss zwischen 1 und 4 liegen (ist {$model->target_quarter}).");
            }

            // Erreichungsdatum folgt dem Status: erreicht → heute (falls nicht gesetzt), sonst leer
            if ($model->status === MilestoneStatus::REACHED) {
                if (empty($model->reached_at)) {
                    $model->reached_at = now()->toDateString();
                }
            } elseif ($model->isDirty('status')) {
                $model->reached_at = null;
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

    /** Was zahlt auf diesen Meilenstein ein (Objectives / Key Results aus dem OKR-Modul). */
    public function contributions(): HasMany
    {
        return $this->hasMany(ManagementBookMilestoneContribution::class, 'milestone_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function getDisplayName(): ?string
    {
        return $this->title;
    }

    public function scopeStatus($query, MilestoneStatus|string $status)
    {
        return $query->where('status', $status instanceof MilestoneStatus ? $status->value : $status);
    }

    public function scopeInQuarter($query, int $year, int $quarter)
    {
        return $query->where('target_year', $year)->where('target_quarter', $quarter);
    }
}
