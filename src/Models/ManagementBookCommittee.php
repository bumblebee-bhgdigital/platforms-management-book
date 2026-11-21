<?php

namespace Platform\ManagementBook\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Contracts\HasDisplayName;
use Platform\ManagementBook\Enums\CommitteeStatus;
use Platform\ManagementBook\Enums\CommitteeType;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Gremium eines Management Books (z. B. Lenkungsausschuss). Reine Datenhaltung: keine Standard-Agenda, keine Ablauflogik — das Wissen, wie ein Gremium arbeitet, liegt beim Agenten.
 *
 * Sichtbarkeit/Rechte werden vom Management Book geerbt (ManagementBookCommitteePolicy).
 *
 * @property-read string $uuid
 */
class ManagementBookCommittee extends Model implements HasDisplayName
{
    use SoftDeletes;

    protected $table = 'management_book_committees';

    protected $fillable = [
        'uuid',
        'team_id',
        'management_book_id',
        'name',
        'type',
        'description',
        'cadence',
        'chair_user_id',
        'responsible_agent_id',
        'signal_set_id',
        'status',
        'created_by',
    ];

    protected $casts = [
        'uuid' => 'string',
        'type' => CommitteeType::class,
        'status' => CommitteeStatus::class,
        'signal_set_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::withTrashed()->where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
            if (empty($model->type)) {
                $model->type = CommitteeType::OTHER;
            }
            if (empty($model->status)) {
                $model->status = CommitteeStatus::ACTIVE;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(ManagementBook::class, 'management_book_id');
    }

    public function chair(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'chair_user_id');
    }

    public function responsibleAgent(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'responsible_agent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ManagementBookCommitteeMember::class, 'committee_id');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(ManagementBookMeeting::class, 'committee_id');
    }

    public function getDisplayName(): ?string
    {
        return $this->name;
    }

    public function scopeStatus(Builder $query, CommitteeStatus|string $status): Builder
    {
        return $query->where($query->getModel()->getTable() . '.status', $status instanceof CommitteeStatus ? $status->value : $status);
    }
}
