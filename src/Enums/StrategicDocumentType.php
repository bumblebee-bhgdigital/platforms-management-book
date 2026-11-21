<?php

namespace Platform\ManagementBook\Enums;

/**
 * Versionierte Texte eines Management Books. Mission und Vision plus die Kapitel, mit denen das Book
 * die Orientierung verdichtet (BROICH.MANAGEMENTMODELL, Lesson „Der Regelkreis“): Woher kommen wir?
 * Wo stehen wir? Wohin wollen wir? Welche Prioritäten gelten? …
 */
enum StrategicDocumentType: string
{
    case MISSION = 'mission';
    case VISION = 'vision';
    case ORIGIN = 'origin';
    case CURRENT_SITUATION = 'current_situation';
    case PRIORITIES = 'priorities';
    case BUSINESS_FIELDS = 'business_fields';
    case PRINCIPLES = 'principles';
    case CAPABILITIES = 'capabilities';
    case COLLABORATION = 'collaboration';

    public function label(): string
    {
        return match ($this) {
            self::MISSION => 'Mission',
            self::VISION => 'Vision',
            self::ORIGIN => 'Herkunft',
            self::CURRENT_SITUATION => 'Standort',
            self::PRIORITIES => 'Prioritäten',
            self::BUSINESS_FIELDS => 'Geschäftsfelder',
            self::PRINCIPLES => 'Führungs- und Entscheidungsgrundsätze',
            self::CAPABILITIES => 'Fähigkeiten',
            self::COLLABORATION => 'Zusammenarbeit',
        };
    }

    /** Leitfrage des Kapitels. */
    public function question(): string
    {
        return match ($this) {
            self::MISSION => 'Warum gibt es uns?',
            self::VISION => 'Wohin wollen wir?',
            self::ORIGIN => 'Woher kommen wir?',
            self::CURRENT_SITUATION => 'Wo stehen wir?',
            self::PRIORITIES => 'Welche Prioritäten gelten?',
            self::BUSINESS_FIELDS => 'Welche Bedeutung hat welches Geschäftsfeld?',
            self::PRINCIPLES => 'Nach welchen Grundsätzen führen und entscheiden wir?',
            self::CAPABILITIES => 'Welche Fähigkeiten müssen wir aufbauen?',
            self::COLLABORATION => 'Was heißt gute Zusammenarbeit?',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::MISSION => 'heroicon-o-heart',
            self::VISION => 'heroicon-o-eye',
            self::ORIGIN => 'heroicon-o-clock',
            self::CURRENT_SITUATION => 'heroicon-o-map-pin',
            self::PRIORITIES => 'heroicon-o-queue-list',
            self::BUSINESS_FIELDS => 'heroicon-o-squares-2x2',
            self::PRINCIPLES => 'heroicon-o-scale',
            self::CAPABILITIES => 'heroicon-o-academic-cap',
            self::COLLABORATION => 'heroicon-o-user-group',
        };
    }

    /** Lesereihenfolge im Book (Herkunft → Standort → Mission/Vision → …). */
    public static function bookOrder(): array
    {
        return [
            self::ORIGIN, self::CURRENT_SITUATION, self::MISSION, self::VISION, self::PRIORITIES,
            self::BUSINESS_FIELDS, self::PRINCIPLES, self::CAPABILITIES, self::COLLABORATION,
        ];
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
