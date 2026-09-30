<?php

namespace App\Tools\SupplierReconciliation\Review;

/**
 * Who settled an item, kept distinct so a human confirmation is never shown
 * as an automatic decision (spec §35).
 */
enum Resolution: string
{
    case Automatic = 'automatic';
    case Confirmed = 'confirmed';
    case ManualMatch = 'manual_match';
    case Rejected = 'rejected';
    case Deferred = 'deferred';
    case Open = 'open';
    case Excluded = 'excluded';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Matched automatically',
            self::Confirmed => 'Confirmed by you',
            self::ManualMatch => 'Matched manually by you',
            self::Rejected => 'Proposed match rejected by you',
            self::Deferred => 'Left for review',
            self::Open => 'Not reviewed yet',
            self::Excluded => 'Not reconciled',
        };
    }

    public function isSettled(): bool
    {
        return in_array($this, [self::Automatic, self::Confirmed, self::ManualMatch, self::Excluded], true);
    }
}
