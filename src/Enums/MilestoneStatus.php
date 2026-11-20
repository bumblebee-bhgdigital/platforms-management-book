<?php

namespace Platform\ManagementBook\Enums;

enum MilestoneStatus: string
{
    case PLANNED = 'planned';
    case IN_PROGRESS = 'in_progress';
    case REACHED = 'reached';
    case MISSED = 'missed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Geplant',
            self::IN_PROGRESS => 'In Arbeit',
            self::REACHED => 'Erreicht',
            self::MISSED => 'Verfehlt',
            self::CANCELLED => 'Verworfen',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
