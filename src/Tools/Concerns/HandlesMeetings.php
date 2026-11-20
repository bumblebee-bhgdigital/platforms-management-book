<?php

namespace Platform\ManagementBook\Tools\Concerns;

use Illuminate\Support\Facades\Gate;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\ManagementBook\Enums\MeetingStatus;
use Platform\ManagementBook\Models\ManagementBookMeeting;

/** Gemeinsame Helfer der management-book.meetings.*-Tools (Serialisierung, Rechte, Validierung). */
trait HandlesMeetings
{
    use HandlesManagementBooks;

    protected function authorizeMeeting(ToolContext $context, string $ability, ManagementBookMeeting $meeting): ?ToolResult
    {
        return Gate::forUser($context->user)->allows($ability, $meeting)
            ? null
            : ToolResult::error('ACCESS_DENIED', "Keine Berechtigung ({$ability}) für Sitzung #{$meeting->id}.");
    }

    /** @return array<string,mixed> */
    protected function meetingToArray(ManagementBookMeeting $m): array
    {
        return [
            'id' => $m->id,
            'uuid' => $m->uuid,
            'team_id' => $m->team_id,
            'committee_id' => $m->committee_id,
            'title' => $m->title,
            'scheduled_at' => $m->scheduled_at?->toIso8601String(),
            'duration_minutes' => $m->duration_minutes,
            'location' => $m->location,
            'external_calendar_ref' => $m->external_calendar_ref,
            'status' => $m->status?->value,
            'status_label' => $m->status?->label(),
            'created_by' => $m->created_by,
            'created_at' => $m->created_at?->toIso8601String(),
            'updated_at' => $m->updated_at?->toIso8601String(),
        ];
    }

    /** Gemeinsame Property-Definitionen für POST/PUT. */
    protected function meetingFieldSchema(): array
    {
        return [
            'title' => ['type' => 'string', 'description' => 'Titel der Sitzung.'],
            'scheduled_at' => ['type' => 'string', 'description' => 'Termin (ISO 8601 oder "YYYY-MM-DD HH:MM", Zeitzone Europe/Berlin).'],
            'duration_minutes' => ['type' => 'integer', 'description' => 'Dauer in Minuten.'],
            'location' => ['type' => 'string', 'description' => 'Ort oder Link (Freitext).'],
            'external_calendar_ref' => ['type' => 'string', 'description' => 'Optional: Verweis auf einen bestehenden Kalendertermin (keine Synchronisation).'],
            'status' => ['type' => 'string', 'enum' => MeetingStatus::values(), 'description' => 'planned (geplant), prepared (vorbereitet), held (durchgeführt), cancelled (abgesagt).'],
        ];
    }

    /** Prüft status/Datum/Dauer; liefert eine Fehlermeldung oder null. */
    protected function validateMeetingInput(array $arguments): ?string
    {
        if (isset($arguments['status']) && ! MeetingStatus::tryFrom((string) $arguments['status'])) {
            return 'status muss einer von ' . implode(', ', MeetingStatus::values()) . ' sein.';
        }
        if (isset($arguments['scheduled_at'])) {
            try {
                \Illuminate\Support\Carbon::parse((string) $arguments['scheduled_at']);
            } catch (\Throwable $e) {
                return 'scheduled_at ist kein gültiges Datum.';
            }
        }
        if (isset($arguments['duration_minutes']) && (int) $arguments['duration_minutes'] < 1) {
            return 'duration_minutes muss mindestens 1 sein.';
        }

        return null;
    }
}
