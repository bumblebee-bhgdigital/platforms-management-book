<?php

namespace Platform\ManagementBook\Enums;

enum CommitteeMemberRole: string
{
    case CHAIR = 'chair';
    case MEMBER = 'member';
    case GUEST = 'guest';
    case SECRETARY = 'secretary';

    public function label(): string
    {
        return match ($this) {
            self::CHAIR => 'Vorsitz',
            self::MEMBER => 'Mitglied',
            self::GUEST => 'Gast',
            self::SECRETARY => 'Protokoll',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
