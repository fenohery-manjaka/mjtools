<?php

namespace App\Tools\SupplierReconciliation\Result;

/**
 * Categories shown to users instead of an uncalibrated percentage (spec §33).
 */
enum Confidence: string
{
    case Certain = 'certain';
    case StrongCandidate = 'strong_candidate';
    case Ambiguous = 'ambiguous';
    case NoMatch = 'no_match';

    public function label(): string
    {
        return match ($this) {
            self::Certain => 'Certain',
            self::StrongCandidate => 'Strong candidate',
            self::Ambiguous => 'Ambiguous',
            self::NoMatch => 'No match',
        };
    }

    public static function forStatus(ItemStatus $status): ?self
    {
        return match ($status) {
            ItemStatus::Matched => self::Certain,
            ItemStatus::PossibleMatch, ItemStatus::AmountMismatch => self::StrongCandidate,
            ItemStatus::Ambiguous, ItemStatus::DuplicateSuspected => self::Ambiguous,
            ItemStatus::MissingInLedger, ItemStatus::LedgerOnly => self::NoMatch,
            ItemStatus::ReviewRequired, ItemStatus::Excluded => null,
        };
    }
}
