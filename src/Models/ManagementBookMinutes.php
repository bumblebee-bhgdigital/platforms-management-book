<?php

namespace Platform\ManagementBook\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Contracts\HasDisplayName;
use Platform\ManagementBook\Models\Concerns\BelongsToMeeting;
use Platform\ManagementBook\Models\Concerns\GeneratesUuid;

/**
 * @ai.description Protokoll einer Sitzung (Markdown). Freigabe über approved_at/approved_by; es gibt keine Freigabe-Automatik.
 *
 * @property-read string $uuid
 */
class ManagementBookMinutes extends Model implements HasDisplayName
{
    use SoftDeletes, GeneratesUuid, BelongsToMeeting;

    protected $table = 'management_book_minutes';

    protected $fillable = [
        'uuid',
        'team_id',
        'meeting_id',
        'content',
        'approved_at',
        'approved_by',
        'created_by',
    ];

    protected $casts = [
        'uuid' => 'string',
        'approved_at' => 'datetime',
    ];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'approved_by');
    }

    public function getDisplayName(): ?string
    {
        return 'Protokoll #' . $this->id;
    }
}
