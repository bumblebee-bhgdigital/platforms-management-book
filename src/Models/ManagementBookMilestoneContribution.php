<?php

namespace Platform\ManagementBook\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Platform\ManagementBook\Models\Concerns\GeneratesUuid;

/**
 * @ai.description Beitrag (Contribution): ein Objekt – typisch Objective oder Key Result aus dem OKR-Modul – zahlt auf einen Meilenstein der Transformation Map des Books ein (Ausrichtung Umsetzung → Strategie).
 *
 * LOSE gekoppelt wie organization_milestone_contributions: `linkable_type` ist ein Morph-Alias (okr_objective, okr_key_result, …),
 * die Existenz wird generisch über die Morph-Map geprüft; das Management-Book-Modul kennt keine OKR-Klassen. Die Brücke zum OKR-Modul
 * bleibt damit unverändert (OKR registriert seine Aliase selbst). `weight` (1–100, optional) ist für eine spätere Fortschritts-Hochrechnung vorgesehen.
 *
 * @property-read string $uuid
 */
class ManagementBookMilestoneContribution extends Model
{
    use GeneratesUuid;

    protected $table = 'management_book_milestone_contributions';

    protected $fillable = [
        'uuid',
        'team_id',
        'management_book_id',
        'milestone_id',
        'linkable_type',
        'linkable_id',
        'weight',
        'created_by_user_id',
    ];

    protected $casts = [
        'uuid' => 'string',
        'milestone_id' => 'integer',
        'linkable_id' => 'integer',
        'weight' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            $alias = static::resolveType((string) $model->linkable_type);
            $class = $alias ? Relation::getMorphedModel($alias) : null;
            if (! $class || ! class_exists($class)) {
                throw new \InvalidArgumentException("Objekttyp '{$model->linkable_type}' ist nicht registriert.");
            }
            $model->linkable_type = $alias;
            if (! $class::query()->whereKey($model->linkable_id)->exists()) {
                throw new \InvalidArgumentException("{$alias} #{$model->linkable_id} nicht gefunden.");
            }

            $milestone = ManagementBookMilestone::query()->find($model->milestone_id);
            if (! $milestone) {
                throw new \InvalidArgumentException("Meilenstein #{$model->milestone_id} nicht gefunden.");
            }
            $model->management_book_id = $milestone->management_book_id;
            if (empty($model->team_id)) {
                $model->team_id = $milestone->team_id;
            }

            if ($model->weight !== null && ($model->weight < 1 || $model->weight > 100)) {
                throw new \InvalidArgumentException("weight muss zwischen 1 und 100 liegen (ist {$model->weight}).");
            }
        });
    }

    /**
     * Kurzname, Morph-Alias oder FQCN → Morph-Alias (oder null).
     * Bevorzugt die ObjectTypeRegistry der Organization (Kurznamen wie "objective"), fällt sonst auf die Morph-Map zurück.
     */
    public static function resolveType(?string $type): ?string
    {
        $type = trim((string) $type);
        if ($type === '') {
            return null;
        }

        if (class_exists(\Platform\Organization\Services\ObjectTypeRegistry::class)) {
            try {
                if ($alias = \Platform\Organization\Services\ObjectTypeRegistry::resolve($type)) {
                    return $alias;
                }
            } catch (\Throwable $e) {
                // Registry nicht verfügbar → Fallback
            }
        }

        $lower = strtolower($type);
        if (Relation::getMorphedModel($lower)) {
            return $lower;
        }

        return array_flip(Relation::morphMap())[ltrim($type, '\\')] ?? null;
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ManagementBookMilestone::class, 'milestone_id');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(ManagementBook::class, 'management_book_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }

    /** Ausrichtung als Array (Tools): worauf zahlt es ein? */
    public function toAlignmentArray(): array
    {
        $m = $this->milestone;

        return [
            'contribution_id' => $this->id,
            'management_book_id' => $this->management_book_id,
            'milestone_id' => $this->milestone_id,
            'milestone' => $m?->title,
            'milestone_status' => $m?->status?->value,
            'focus_area_id' => $m?->focus_area_id,
            'focus_area' => $m?->focusArea?->title,
            'target_year' => $m?->target_year,
            'target_quarter' => $m?->target_quarter,
            'weight' => $this->weight,
        ];
    }
}
