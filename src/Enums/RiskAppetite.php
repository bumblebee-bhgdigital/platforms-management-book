<?php

namespace Platform\ManagementBook\Enums;

/**
 * Risikobereitschaft eines Management Books (Risiko-Skala, Q-Meeting „Risikomanagement“, Folie 10).
 * Einstellung am Book; rein beschreibend, keine Ablauflogik.
 */
enum RiskAppetite: string
{
    case VERY_LOW = 'very_low';
    case LOW = 'low';
    case MODERATE = 'moderate';
    case HIGH = 'high';
    case VERY_HIGH = 'very_high';

    public function label(): string
    {
        return match ($this) {
            self::VERY_LOW => 'Sehr geringes Risiko',
            self::LOW => 'Geringes Risiko',
            self::MODERATE => 'Moderates Risiko',
            self::HIGH => 'Hohes Risiko',
            self::VERY_HIGH => 'Sehr hohes Risiko',
        };
    }

    /** Akzeptiertes Level an Unsicherheit je Stufe. */
    public function acceptedUncertainty(): string
    {
        return match ($this) {
            self::VERY_LOW => 'sehr gering',
            self::LOW => 'gering',
            self::MODERATE => 'begrenzt',
            self::HIGH => 'erwartet',
            self::VERY_HIGH => 'vollkommen angenommen',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
