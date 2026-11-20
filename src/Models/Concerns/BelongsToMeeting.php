<?php

namespace Platform\ManagementBook\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\ManagementBook\Models\ManagementBookMeeting;

/**
 * Gemeinsame Basis der Sitzungs-Kinder: team_id wird aus der Sitzung abgeleitet, Rechte werden über die Sitzung
 * (und damit Gremium und Management Book) geerbt (ManagementBookMeetingChildPolicy).
 */
trait BelongsToMeeting
{
    public static function bootBelongsToMeeting(): void
    {
        static::saving(function ($model) {
            $meeting = ManagementBookMeeting::withTrashed()->find($model->meeting_id);
            if (! $meeting) {
                throw new \InvalidArgumentException("meeting_id {$model->meeting_id} nicht gefunden.");
            }
            if (empty($model->team_id)) {
                $model->team_id = $meeting->team_id;
            }
        });
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(ManagementBookMeeting::class, 'meeting_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }
}
