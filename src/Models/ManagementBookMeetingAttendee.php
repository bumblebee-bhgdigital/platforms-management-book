<?php

namespace Platform\ManagementBook\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\ManagementBook\Models\Concerns\BelongsToMeeting;

/**
 * @ai.description Teilnehmer einer Sitzung: genau eines von user_id, agent_id (Agent = User vom Typ ai_user) oder organization_entity_id (Personen-Knoten ohne Plattform-Konto), freie Rolle, attended (tatsächlich anwesend).
 */
class ManagementBookMeetingAttendee extends Model
{
    use BelongsToMeeting;

    protected $table = 'management_book_meeting_attendees';

    protected $fillable = [
        'team_id',
        'meeting_id',
        'user_id',
        'agent_id',
        'organization_entity_id',
        'role',
        'attended',
    ];

    protected $casts = [
        'attended' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'user_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'agent_id');
    }

    /** Personen-Knoten der Organisation (Teilnehmer ohne Plattform-Konto). */
    public function person(): BelongsTo
    {
        return $this->belongsTo(\Platform\Organization\Models\OrganizationEntity::class, 'organization_entity_id');
    }

    public function displayName(): string
    {
        if ($this->user_id || $this->agent_id) {
            return $this->user?->name ?? $this->agent?->name ?? '–';
        }

        return $this->organization_entity_id && class_exists(\Platform\Organization\Models\OrganizationEntity::class)
            ? ($this->person?->name ?? '–') : '–';
    }
}
