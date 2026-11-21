<?php

namespace Platform\ManagementBook\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Contracts\HasDisplayName;
use Platform\ManagementBook\Enums\MeetingStatus;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Sitzung (Termin) eines Gremiums. Reine Datenhaltung: keine Automatik, keine Kalender-Synchronisation, keine Bindung an Huddles.
 *
 * Sichtbarkeit/Rechte werden über Gremium und Management Book geerbt (ManagementBookMeetingPolicy).
 *
 * @property-read string $uuid
 */
class ManagementBookMeeting extends Model implements HasDisplayName
{
    /** Morph-Alias (z. B. für Slides-Decks, die lose an einer Sitzung hängen). */
    public const MORPH_ALIAS = 'management_book_meeting';

    use SoftDeletes;

    protected $table = 'management_book_meetings';

    protected $fillable = [
        'uuid',
        'team_id',
        'committee_id',
        'title',
        'scheduled_at',
        'duration_minutes',
        'location',
        'external_calendar_ref',
        'status',
        'created_by',
    ];

    protected $casts = [
        'uuid' => 'string',
        'scheduled_at' => 'datetime',
        'duration_minutes' => 'integer',
        'status' => MeetingStatus::class,
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
            if (empty($model->status)) {
                $model->status = MeetingStatus::PLANNED;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(ManagementBookCommittee::class, 'committee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by');
    }

    // Kinder (#1052). Soft-Delete der Sitzung kaskadiert nicht (wie Gremium -> Sitzung): die Kinder sind über die
    // Policy an die Sitzung gebunden und bei gelöschter Sitzung nicht mehr les-/schreibbar. DB-FK: cascadeOnDelete.

    public function agendaItems(): HasMany
    {
        return $this->hasMany(ManagementBookAgendaItem::class, 'meeting_id')->orderBy('position')->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ManagementBookDocument::class, 'meeting_id');
    }

    public function minutes(): HasMany
    {
        return $this->hasMany(ManagementBookMinutes::class, 'meeting_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ManagementBookDecision::class, 'meeting_id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(ManagementBookMeetingAttendee::class, 'meeting_id');
    }

    public function getDisplayName(): ?string
    {
        return $this->title;
    }

    public function scopeStatus(Builder $query, MeetingStatus|string $status): Builder
    {
        return $query->where($query->getModel()->getTable() . '.status', $status instanceof MeetingStatus ? $status->value : $status);
    }

    /** Anstehende Sitzungen: ab jetzt, nicht abgesagt/durchgeführt. */
    public function scopeUpcoming(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->where($table . '.scheduled_at', '>=', now())
            ->whereNotIn($table . '.status', [MeetingStatus::CANCELLED->value, MeetingStatus::HELD->value]);
    }
}
