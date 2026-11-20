<?php

namespace Platform\ManagementBook\Enums;

enum StrategicDocumentType: string
{
    case MISSION = 'mission';
    case VISION = 'vision';

    public function label(): string
    {
        return match ($this) {
            self::MISSION => 'Mission',
            self::VISION => 'Vision',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
