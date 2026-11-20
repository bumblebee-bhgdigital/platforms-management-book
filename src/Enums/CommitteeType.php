<?php

namespace Platform\ManagementBook\Enums;

/** Gremium-Typ. Fest, damit der Regelkreis (MGT-101) erkennbar bleibt; die Ablauflogik liegt beim Agenten. */
enum CommitteeType: string
{
    case STEERING_COMMITTEE = 'steering_committee';
    case STRATEGY_MEETING = 'strategy_meeting';
    case BOARD = 'board';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::STEERING_COMMITTEE => 'Lenkungsausschuss',
            self::STRATEGY_MEETING => 'Strategie-Meeting',
            self::BOARD => 'Board',
            self::OTHER => 'Sonstiges',
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
