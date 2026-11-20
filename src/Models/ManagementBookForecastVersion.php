<?php

namespace Platform\ManagementBook\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\ManagementBook\Models\Concerns\GeneratesUuid;

/**
 * @ai.description Versionierter Snapshot des Forecast-Inhalts. Rechte werden über den Forecast (und das Management Book) geerbt.
 *
 * @property-read string $uuid
 */
class ManagementBookForecastVersion extends Model
{
    use GeneratesUuid;

    protected $table = 'management_book_forecast_versions';

    protected $fillable = [
        'uuid',
        'forecast_id',
        'version',
        'content',
        'change_note',
        'created_by',
    ];

    protected $casts = [
        'uuid' => 'string',
        'version' => 'integer',
    ];

    public function forecast(): BelongsTo
    {
        return $this->belongsTo(ManagementBookForecast::class, 'forecast_id');
    }
}
