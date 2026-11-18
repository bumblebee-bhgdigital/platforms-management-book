<?php

namespace Platform\ManagementBook\Enums;

/**
 * Status eines Management Books.
 *
 * Übergänge: draft -> active -> superseded (automatisch beim Aktivieren eines anderen Books
 * am selben Knoten); jeder Status -> archived. superseded -> active ist erlaubt (Wiederinkraftsetzen).
 */
enum ManagementBookStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case SUPERSEDED = 'superseded';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Entwurf',
            self::ACTIVE => 'Aktiv',
            self::SUPERSEDED => 'Abgelöst',
            self::ARCHIVED => 'Archiviert',
        };
    }

    /** nx-badge variant */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::DRAFT => 'neutral',
            self::ACTIVE => 'success',
            self::SUPERSEDED => 'warning',
            self::ARCHIVED => 'neutral',
        };
    }

    public function canActivate(): bool
    {
        return in_array($this, [self::DRAFT, self::SUPERSEDED, self::ACTIVE], true);
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
