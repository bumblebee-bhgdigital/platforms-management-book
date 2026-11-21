<?php

namespace Platform\ManagementBook\Enums;

/**
 * Ampel nach Risikopriorität (E × A, 1–25) gemäß Risikomatrix-Vorlage (#1048).
 * Wird nicht gespeichert, sondern immer aus dem Score abgeleitet.
 */
enum RiskLevel: string
{
    case LOW = 'low';
    case MODERATE = 'moderate';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    public static function fromScore(?int $score): ?self
    {
        return match (true) {
            $score === null || $score < 1 => null,
            $score <= 4 => self::LOW,
            $score <= 9 => self::MODERATE,
            $score <= 15 => self::HIGH,
            default => self::CRITICAL,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Niedrig',
            self::MODERATE => 'Moderat',
            self::HIGH => 'Hoch',
            self::CRITICAL => 'Kritisch',
        };
    }

    /** Ampelfarbe der Vorlage. */
    public function color(): string
    {
        return match ($this) {
            self::LOW => 'green',
            self::MODERATE => 'yellow',
            self::HIGH => 'orange',
            self::CRITICAL => 'red',
        };
    }

    /** Handlungsempfehlung der Vorlage (rein informativ, keine Ablauflogik). */
    public function guidance(): string
    {
        return match ($this) {
            self::LOW => 'Beobachten',
            self::MODERATE => 'Maßnahmen prüfen',
            self::HIGH => 'Maßnahmen umsetzen, überwachen',
            self::CRITICAL => 'Sofortiges Handeln erforderlich',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::LOW => 'success',
            self::MODERATE => 'warning',
            self::HIGH => 'warning',
            self::CRITICAL => 'danger',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
