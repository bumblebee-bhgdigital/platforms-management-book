<?php

namespace Platform\ManagementBook\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Contracts\HasDisplayName;
use Platform\ManagementBook\Enums\RiskLevel;
use Platform\ManagementBook\Enums\RiskStatus;
use Platform\ManagementBook\Models\Concerns\GeneratesUuid;

/**
 * @ai.description Risiko eines Management Books (Zeile der Risikomatrix): Eintrittswahrscheinlichkeit E (1–5) × Auswirkung A (1–5) = Risikopriorität (Score 1–25), daraus die Ampel (niedrig/moderat/hoch/kritisch). Bereich/Abteilung hängt lose als Knoten der Organisation (organization_entity_id). Reine Datenhaltung, keine Ablauflogik.
 *
 * @property-read string $uuid
 */
class ManagementBookRisk extends Model implements HasDisplayName
{
    use SoftDeletes, GeneratesUuid;

    protected $table = 'management_book_risks';

    protected $fillable = [
        'uuid',
        'team_id',
        'management_book_id',
        'number',
        'title',
        'description',
        'organization_entity_id',
        'probability',
        'impact',
        'category',
        'measures_planned',
        'measures_implemented',
        'owner_user_id',
        'owner_agent_id',
        'due_date',
        'status',
        'review_date',
        'created_by',
    ];

    protected $casts = [
        'uuid' => 'string',
        'organization_entity_id' => 'integer',
        'probability' => 'integer',
        'impact' => 'integer',
        'score' => 'integer',
        'status' => RiskStatus::class,
        'due_date' => 'date',
        'review_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            // Risikopriorität = E × A; fehlt einer der Werte, gibt es keinen Score (und keine Ampel).
            $model->score = ($model->probability && $model->impact) ? $model->probability * $model->impact : null;
            if (empty($model->status)) {
                $model->status = RiskStatus::OPEN;
            }
        });
    }

    public function level(): ?RiskLevel
    {
        return RiskLevel::fromScore($this->score);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(ManagementBook::class, 'management_book_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'owner_user_id');
    }

    public function ownerAgent(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'owner_agent_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(\Platform\Organization\Models\OrganizationEntity::class, 'organization_entity_id');
    }

    public function getDisplayName(): ?string
    {
        return $this->number ? "{$this->number} {$this->title}" : $this->title;
    }
}
