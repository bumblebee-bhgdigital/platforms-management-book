<?php

namespace Platform\ManagementBook\Enums;

enum MeetingStatus: string
{
    case PLANNED = 'planned';
    case PREPARED = 'prepared';
    case HELD = 'held';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Geplant',
            self::PREPARED => 'Vorbereitet',
            self::HELD => 'Durchgeführt',
            self::CANCELLED => 'Abgesagt',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
