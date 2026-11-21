<?php

namespace Platform\ManagementBook\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Contracts\HasDisplayName;
use Platform\ManagementBook\Enums\DecisionStatus;
use Platform\ManagementBook\Models\Concerns\BelongsToMeeting;
use Platform\ManagementBook\Models\Concerns\GeneratesUuid;

/**
 * @ai.description Beschluss einer Sitzung (optional zu einem Agenda-Punkt) mit Status offen/umgesetzt/verworfen, Verantwortlichem und Fälligkeit.
 * linked_task_id verweist lose auf eine Planner-Aufgabe (keine automatische Anlage/Synchronisation).
 *
 * @property-read string $uuid
 */
class ManagementBookDecision extends Model implements HasDisplayName
{
    use SoftDeletes, GeneratesUuid, BelongsToMeeting;

    protected $table = 'management_book_decisions';

    protected $fillable = [
        'uuid',
        'team_id',
        'meeting_id',
        'agenda_item_id',
        'text',
        'kind',
        'status',
        'owner_user_id',
        'owner_agent_id',
        'due_date',
        'linked_task_id',
        'created_by',
    ];

    protected $casts = [
        'uuid' => 'string',
        'status' => DecisionStatus::class,
        'due_date' => 'date',
        'linked_task_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->status)) {
                $model->status = DecisionStatus::OPEN;
            }
        });
        static::saving(function (self $model) {
            if ($model->agenda_item_id) {
                $item = ManagementBookAgendaItem::withTrashed()->find($model->agenda_item_id);
                if (! $item || (int) $item->meeting_id !== (int) $model->meeting_id) {
                    throw new \InvalidArgumentException('agenda_item_id gehört nicht zu dieser Sitzung.');
                }
            }
        });
    }

    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(ManagementBookAgendaItem::class, 'agenda_item_id');
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'owner_user_id');
    }

    public function ownerAgent(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'owner_agent_id');
    }

    public function getDisplayName(): ?string
    {
        return \Illuminate\Support\Str::limit((string) $this->text, 80);
    }
}
