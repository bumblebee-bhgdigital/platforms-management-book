<?php

namespace Platform\ManagementBook\Tools\Concerns;

use Illuminate\Support\Carbon;
use Platform\ManagementBook\Enums\MilestoneStatus;
use Platform\ManagementBook\Models\ManagementBookMilestone;

/** Gemeinsame Helfer der management-book.transformation_map.*-Tools. */
trait HandlesTransformationMap
{
    use HandlesFocusAreaItems;

    /** Ende des Zielzeitraums eines Waypoints (Datum > Quartalsende > Jahresende), oder null ohne Termin. */
    protected function waypointDueDate(ManagementBookMilestone $m): ?Carbon
    {
        if ($m->target_date) {
            return $m->target_date->copy()->endOfDay();
        }
        if ($m->target_year && $m->target_quarter) {
            return Carbon::create((int) $m->target_year, ((int) $m->target_quarter) * 3, 1)->endOfMonth();
        }
        if ($m->target_year) {
            return Carbon::create((int) $m->target_year, 12, 31)->endOfDay();
        }

        return null;
    }

    /** Offen = noch nicht erreicht/verfehlt/verworfen. */
    protected function isOpenWaypoint(ManagementBookMilestone $m): bool
    {
        return in_array($m->status, [MilestoneStatus::PLANNED, MilestoneStatus::IN_PROGRESS], true);
    }
}
