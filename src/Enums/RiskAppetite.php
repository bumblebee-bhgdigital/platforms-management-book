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

    /** Risiko-Skala (Folie 10): je Stufe die vier Dimensionen der Risikobereitschaft. */
    public function profile(): array
    {
        return match ($this) {
            self::VERY_LOW => ['Unternehmen riskiert so wenig wie möglich', 'Wahl der sichersten Option', 'Negative Folgen werden nicht akzeptiert', 'Sehr gering'],
            self::LOW => ['Unternehmen geht vorsichtig mit Risiken um', 'Wahl riskanter Optionen, wenn unverzichtbar', 'Akzeptiert nur unwahrscheinliche negative Folgen', 'Gering'],
            self::MODERATE => ['Unternehmen steht ausgeglichen zu Risiken', 'Wahl riskanter Optionen, wenn sehr viele Vorteile', 'Negative Folgen werden mit Zielen abgewogen', 'Begrenzt'],
            self::HIGH => ['Unternehmen nimmt größere Risiken in Kauf', 'Wahl riskanter Optionen, aber Folgen beobachtet', 'Negative Folgen akzeptiert, um Ziele zu erreichen', 'Erwartet'],
            self::VERY_HIGH => ['Unternehmen hat eine unkontrollierte Risikobereitschaft', 'Wahl von riskanten Optionen', 'Negative Folgen akzeptiert oder hingenommen', 'Vollkommen angenommen'],
        };
    }

    /** Zeilenbeschriftungen zu profile(). */
    public static function profileDimensions(): array
    {
        return ['Risikobereitschaft', 'Wahl bei mehreren Optionen', 'Haltung zu negativen Folgen', 'Akzeptiertes Level an Unsicherheit'];
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
