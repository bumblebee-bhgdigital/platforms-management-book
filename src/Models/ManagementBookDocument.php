<?php

namespace Platform\ManagementBook\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Contracts\HasDisplayName;
use Platform\Core\Traits\HasContextFileReferences;
use Platform\ManagementBook\Models\Concerns\BelongsToMeeting;
use Platform\ManagementBook\Models\Concerns\GeneratesUuid;

/**
 * @ai.description Unterlage zu einer Sitzung (optional zu einem Agenda-Punkt). Die Datei liegt in den Core-ContextFiles
 * (Verknüpfung über context_file_references, wie Brands-Moodboard); type ist frei.
 *
 * @property-read string $uuid
 */
class ManagementBookDocument extends Model implements HasDisplayName
{
    use SoftDeletes, GeneratesUuid, BelongsToMeeting, HasContextFileReferences;

    protected $table = 'management_book_documents';

    protected $fillable = [
        'uuid',
        'team_id',
        'meeting_id',
        'agenda_item_id',
        'title',
        'type',
        'created_by',
    ];

    protected $casts = [
        'uuid' => 'string',
    ];

    protected static function booted(): void
    {
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

    public function getDisplayName(): ?string
    {
        return $this->title;
    }
}
