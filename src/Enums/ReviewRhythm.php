<?php

namespace Platform\ManagementBook\Enums;

enum ReviewRhythm: string
{
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case SEMIANNUAL = 'semiannual';
    case YEARLY = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::MONTHLY => 'Monatlich',
            self::QUARTERLY => 'Quartalsweise',
            self::SEMIANNUAL => 'Halbjährlich',
            self::YEARLY => 'Jährlich',
        };
    }

    public function months(): int
    {
        return match ($this) {
            self::MONTHLY => 1,
            self::QUARTERLY => 3,
            self::SEMIANNUAL => 6,
            self::YEARLY => 12,
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
