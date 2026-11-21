<?php

namespace Platform\ManagementBook\Enums;

/**
 * Maßstab eines Lenkungsausschuss-Punkts: Ist die Organisation danach handlungsfähiger als vorher?
 * Sechs Kriterien aus dem BROICH.MANAGEMENTMODELL („Die vier Fragen“). Rein beschreibend.
 */
enum AgendaOutcomeCheck: string
{
    case BLOCKERS_REMOVED = 'blockers_removed';
    case PRIORITIES_CLEARER = 'priorities_clearer';
    case CONFLICTS_DECIDED = 'conflicts_decided';
    case RESPONSIBILITIES_CLEAR = 'responsibilities_clear';
    case RESOURCES_AVAILABLE = 'resources_available';
    case UNIT_SELF_RELIANT = 'unit_self_reliant';

    public function label(): string
    {
        return match ($this) {
            self::BLOCKERS_REMOVED => 'Blockaden sind beseitigt',
            self::PRIORITIES_CLEARER => 'Prioritäten sind klarer',
            self::CONFLICTS_DECIDED => 'Zielkonflikte sind entschieden',
            self::RESPONSIBILITIES_CLEAR => 'Verantwortlichkeiten sind eindeutig',
            self::RESOURCES_AVAILABLE => 'Notwendige Ressourcen sind verfügbar',
            self::UNIT_SELF_RELIANT => 'Die Einheit kann wieder selbstständig handeln',
        };
    }

    /** Einordnung nach Anzahl erfüllter Kriterien (Formulierung aus dem Kurs). */
    public static function verdict(int $fulfilled): array
    {
        return match (true) {
            $fulfilled >= 5 => ['variant' => 'success', 'label' => 'Handlungsfähiger als vorher'],
            $fulfilled >= 3 => ['variant' => 'warning', 'label' => 'Teilweise handlungsfähiger'],
            default => ['variant' => 'danger', 'label' => 'Eher berichtet als reguliert'],
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
