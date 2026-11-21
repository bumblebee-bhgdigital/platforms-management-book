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
 * @ai.description Gremium eines Management Books (z. B. Lenkungsausschuss). Keine Standard-Agenda, keine Ablauflogik; das Format je Typ (format()) legt Agenda-Felder, Arten von Punkten/Beschlüssen und die Erfolgsmessung fest. Verortung an einem Organisations-Knoten lose per DimensionLink (Alias management_book_committee).
 *
 * Sichtbarkeit/Rechte werden vom Management Book geerbt (ManagementBookCommitteePolicy).
 *
 * @property-read string $uuid
 */
class ManagementBookCommittee extends Model implements HasDisplayName
{
    /** Morph-Alias (z. B. für Slides-Decks, die lose an einem Gremium hängen). */
    public const MORPH_ALIAS = 'management_book_committee';

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

    /** Format des Gremiums nach Typ (src/Formats). */
    public function format(): \Platform\ManagementBook\Formats\CommitteeFormat
    {
        return \Platform\ManagementBook\Formats\CommitteeFormat::for($this->type);
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
