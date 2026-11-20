<?php

namespace Platform\ManagementBook\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\HasDisplayName;
use Platform\ManagementBook\Models\Concerns\GeneratesUuid;

/**
 * @ai.description Forecast (Regnose) eines Management Books mit Zieljahr/-datum. Der Inhalt ist versionierbar; current_version_id zeigt auf die aktuell dargestellte Version.
 *
 * @property-read string $uuid
 */
class ManagementBookForecast extends Model implements HasDisplayName
{
    use SoftDeletes, GeneratesUuid;

    protected $table = 'management_book_forecasts';

    protected $fillable = [
        'uuid',
        'team_id',
        'management_book_id',
        'title',
        'target_date',
        'content',
        'current_version_id',
        'created_by',
    ];

    protected $casts = [
        'uuid' => 'string',
        'target_date' => 'date',
        'current_version_id' => 'integer',
    ];

    /** Neue Version anlegen und als aktuelle markieren (Historie bleibt erhalten). */
    public function createNewVersion(string $content, ?string $changeNote = null, ?int $userId = null): ManagementBookForecastVersion
    {
        return DB::transaction(function () use ($content, $changeNote, $userId) {
            $nextVersion = 1 + (int) $this->versions()->max('version');

            $version = $this->versions()->create([
                'version' => $nextVersion,
                'content' => $content,
                'change_note' => $changeNote,
                'created_by' => $userId,
            ]);

            $this->update([
                'content' => $content,
                'current_version_id' => $version->id,
            ]);

            return $version;
        });
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(ManagementBook::class, 'management_book_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ManagementBookForecastVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ManagementBookForecastVersion::class, 'forecast_id')->orderBy('version', 'desc');
    }

    public function focusAreas(): HasMany
    {
        return $this->hasMany(ManagementBookFocusArea::class, 'forecast_id')->orderBy('order');
    }

    public function getDisplayName(): ?string
    {
        return $this->title;
    }
}
