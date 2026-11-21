<?php

namespace Platform\ManagementBook\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Contracts\HasDisplayName;
use Platform\ManagementBook\Models\Concerns\BelongsToMeeting;
use Platform\ManagementBook\Models\Concerns\GeneratesUuid;

/**
 * @ai.description Agenda-Punkt einer Sitzung. Reine Datenhaltung: der Agent entscheidet, was auf die Agenda kommt; es gibt keine Standard-Agenda.
 * Optional verweist linked_object (Morph-Alias + ID) lose auf ein beliebiges Objekt (Meilenstein, Risiko, Signal, ...).
 *
 * @property-read string $uuid
 */
class ManagementBookAgendaItem extends Model implements HasDisplayName
{
    use SoftDeletes, GeneratesUuid, BelongsToMeeting;

    protected $table = 'management_book_agenda_items';

    protected $fillable = [
        'uuid',
        'team_id',
        'meeting_id',
        'position',
        'title',
        'kind',
        'description',
        'owner_user_id',
        'owner_agent_id',
        'duration_minutes',
        'linked_object_type',
        'linked_object_id',
        'goal',
        'observation',
        'obstacle',
        'local_solution',
        'system_support',
        'organization_entity_id',
        'outcome_checks',
        'proposal_status',
        'local_solution_confirmed',
        'created_by',
    ];

    protected $casts = [
        'uuid' => 'string',
        'position' => 'integer',
        'duration_minutes' => 'integer',
        'linked_object_id' => 'integer',
        'organization_entity_id' => 'integer',
        'outcome_checks' => 'array',
        'local_solution_confirmed' => 'boolean',
    ];

    public const PROPOSAL_DRAFT = 'draft';
    public const PROPOSAL_ACCEPTED = 'accepted';

    public function isProposalDraft(): bool
    {
        return $this->proposal_status === self::PROPOSAL_DRAFT;
    }

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if ($model->linked_object_type !== null && $model->linked_object_type !== '') {
                $alias = ManagementBookMilestoneContribution::resolveType((string) $model->linked_object_type);
                $class = $alias ? \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($alias) : null;
                if (! $class || ! class_exists($class)) {
                    throw new \InvalidArgumentException("Objekttyp '{$model->linked_object_type}' ist nicht registriert.");
                }
                if (empty($model->linked_object_id) || ! $class::query()->whereKey($model->linked_object_id)->exists()) {
                    throw new \InvalidArgumentException("{$alias} #{$model->linked_object_id} nicht gefunden.");
                }
                $model->linked_object_type = $alias;
            } else {
                $model->linked_object_type = null;
                $model->linked_object_id = null;
            }
        });
    }

    public function linkedObject(): MorphTo
    {
        return $this->morphTo('linked_object', 'linked_object_type', 'linked_object_id');
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'owner_user_id');
    }

    public function ownerAgent(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'owner_agent_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ManagementBookDocument::class, 'agenda_item_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ManagementBookDecision::class, 'agenda_item_id');
    }

    public function getDisplayName(): ?string
    {
        return $this->title;
    }
}
