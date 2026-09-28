<?php

namespace App\Tools\SupplierReconciliation\Review;

enum DecisionAction: string
{
    /** The proposed link is right. */
    case Confirm = 'confirm';
    /** The proposed (or automatic) link is wrong. */
    case Reject = 'reject';
    /** The user links lines themselves (including picking one candidate). */
    case Match = 'match';
    /** The user does not know yet. */
    case Defer = 'defer';

    public function label(): string
    {
        return match ($this) {
            self::Confirm => 'Confirmed by you',
            self::Reject => 'Rejected by you',
            self::Match => 'Matched manually by you',
            self::Defer => 'Left for review',
        };
    }
}
