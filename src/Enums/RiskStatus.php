<?php

namespace Platform\ManagementBook\Enums;

enum RiskStatus: string
{
    case OPEN = 'open';
    case MONITORED = 'monitored';
    case TREATED = 'treated';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Offen',
            self::MONITORED => 'Beobachtet',
            self::TREATED => 'Behandelt',
            self::CLOSED => 'Geschlossen',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::OPEN => 'warning',
            self::MONITORED => 'info',
            self::TREATED => 'success',
            self::CLOSED => 'neutral',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
