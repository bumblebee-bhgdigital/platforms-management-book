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

    /** Bewertungsskala Eintrittswahrscheinlichkeit E (Vorlage, Blatt „Bewertung“). */
    public static function probabilityLabels(): array
    {
        return [1 => 'sehr unwahrscheinlich', 2 => 'unwahrscheinlich', 3 => 'möglich', 4 => 'wahrscheinlich', 5 => 'sehr wahrscheinlich'];
    }

    /** Bewertungsskala Auswirkung A (Vorlage, Blatt „Bewertung“). */
    public static function impactLabels(): array
    {
        return [
            1 => 'gering (kaum Einfluss)',
            2 => 'moderat (behebbar, geringer Aufwand)',
            3 => 'deutlich (erhöhte Kosten, Kundenreklamationen)',
            4 => 'schwerwiegend (Image, Prozessausfall)',
            5 => 'existenzbedrohend (massiver Schaden)',
        ];
    }

    /** Hintergrund-Tönung je Stufe (für Heatmap/Matrix, nx-Semantikfarben). */
    public function tint(): string
    {
        return match ($this) {
            self::LOW => 'rgba(47,158,68,.16)',
            self::MODERATE => 'rgba(230,180,0,.20)',
            self::HIGH => 'rgba(232,89,12,.20)',
            self::CRITICAL => 'rgba(224,49,49,.22)',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
