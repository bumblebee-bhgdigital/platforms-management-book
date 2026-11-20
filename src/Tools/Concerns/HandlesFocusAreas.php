<?php

namespace Platform\ManagementBook\Tools\Concerns;

use Platform\Core\Models\Team;
use Platform\ManagementBook\Models\ManagementBookForecast;

/** Gemeinsame Helfer der management-book.focus_areas.*-Tools. */
trait HandlesFocusAreas
{
    use HandlesStrategy;

    protected function focusAreaFieldSchema(): array
    {
        return [
            'title' => ['type' => 'string', 'description' => 'Titel des Fokusraums.'],
            'description' => ['type' => 'string', 'description' => 'Beschreibung.'],
            'content' => ['type' => 'string', 'description' => 'Optional: Markdown-Inhalt.'],
            'central_question_vision_images' => ['type' => 'string', 'description' => 'Leitfrage der Spalte Zielbilder.'],
            'central_question_obstacles' => ['type' => 'string', 'description' => 'Leitfrage der Spalte Hindernisse.'],
            'central_question_milestones' => ['type' => 'string', 'description' => 'Leitfrage der Spalte Meilensteine.'],
            'order' => ['type' => 'integer', 'description' => 'Reihenfolge (Default 0).'],
            'forecast_id' => ['type' => 'integer', 'description' => 'Optional: Forecast desselben Books (Soft-Link; Fokusräume hängen primär am Book). 0/null löst die Verknüpfung.'],
        ];
    }

    /** Forecast-Link prüfen: muss zum selben Book gehören. Liefert Fehlermeldung oder null. */
    protected function validateForecastLink(mixed $forecastId, int $bookId, Team $team): ?string
    {
        if (empty($forecastId)) {
            return null;
        }
        $forecast = ManagementBookForecast::query()->where('team_id', $team->id)->find((int) $forecastId);

        return ($forecast && (int) $forecast->management_book_id === $bookId)
            ? null
            : 'forecast_id nicht gefunden oder gehört zu einem anderen Management Book.';
    }
}
