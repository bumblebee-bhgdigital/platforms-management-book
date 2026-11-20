<?php

namespace Platform\ManagementBook\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\ManagementBook\Models\Concerns\BelongsToMeeting;

/**
 * @ai.description Teilnehmer einer Sitzung: entweder user_id oder agent_id (Agent = User vom Typ ai_user), freie Rolle, attended (tatsächlich anwesend).
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
}
