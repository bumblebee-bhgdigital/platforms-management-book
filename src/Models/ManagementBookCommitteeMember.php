<?php

namespace Platform\ManagementBook\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\ManagementBook\Enums\CommitteeMemberRole;

/** Mitglied eines Gremiums: entweder user_id oder agent_id (Agent = User vom Typ ai_user) plus Rolle. */
class ManagementBookCommitteeMember extends Model
{
    protected $table = 'management_book_committee_members';

    protected $fillable = ['committee_id', 'user_id', 'agent_id', 'role'];

    protected $casts = [
        'role' => CommitteeMemberRole::class,
    ];

    public function committee(): BelongsTo
    {
        return $this->belongsTo(ManagementBookCommittee::class, 'committee_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'user_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'agent_id');
    }
}
