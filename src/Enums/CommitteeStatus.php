<?php

namespace Platform\ManagementBook\Enums;

enum CommitteeStatus: string
{
    case ACTIVE = 'active';
    case DORMANT = 'dormant';
    case DISSOLVED = 'dissolved';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktiv',
            self::DORMANT => 'Ruhend',
            self::DISSOLVED => 'Aufgelöst',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
