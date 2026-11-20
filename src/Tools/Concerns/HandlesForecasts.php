<?php

namespace Platform\ManagementBook\Tools\Concerns;

use Illuminate\Support\Carbon;
use Platform\Core\Models\Team;
use Platform\ManagementBook\Models\ManagementBookForecast;

/** Gemeinsame Helfer der management-book.forecasts.*-Tools. */
trait HandlesForecasts
{
    use HandlesStrategy;

    protected function findForecast(int $id, Team $team): ?ManagementBookForecast
    {
        return ManagementBookForecast::query()->where('team_id', $team->id)->find($id);
    }

    /**
     * Zieldatum aus target_date oder target_year (→ 31.12.) bestimmen.
     *
     * @return array{0: ?string, 1: ?string} [Datum YYYY-MM-DD|null, Fehlermeldung|null]
     */
    protected function resolveTargetDate(array $arguments): array
    {
        if (! empty($arguments['target_date'])) {
            try {
                return [Carbon::parse((string) $arguments['target_date'])->toDateString(), null];
            } catch (\Throwable $e) {
                return [null, 'target_date ist kein gültiges Datum (YYYY-MM-DD).'];
            }
        }
        if (! empty($arguments['target_year'])) {
            $year = (int) $arguments['target_year'];
            if ($year < 1900 || $year > 2200) {
                return [null, 'target_year ist ungültig.'];
            }

            return [sprintf('%04d-12-31', $year), null];
        }

        return [null, null];
    }
}
