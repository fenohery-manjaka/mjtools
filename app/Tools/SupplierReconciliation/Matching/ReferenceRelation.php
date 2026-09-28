<?php

namespace App\Tools\SupplierReconciliation\Matching;

use App\Tools\SupplierReconciliation\Result\Polarity;

/**
 * How two references relate, from strongest to weakest.
 */
enum ReferenceRelation: int
{
    case Identical = 1;
    case Formatting = 2;
    case LeadingZeros = 3;
    case PrefixMissing = 4;
    case PrefixDifferent = 5;
    case Similar = 6;
    case Different = 7;
    case Unavailable = 8;

    /**
     * Strong enough, together with other evidence, for an automatic match.
     */
    public function isStrong(): bool
    {
        return $this->value <= self::LeadingZeros->value;
    }

    /**
     * Same document number, possibly written differently. Such a link is a
     * competitor that prevents any automatic match (spec §19).
     */
    public function isSameNumber(): bool
    {
        return $this->value <= self::PrefixDifferent->value;
    }

    public function isRelated(): bool
    {
        return $this->value <= self::Similar->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Identical => 'Identical',
            self::Formatting => 'Same after ignoring formatting',
            self::LeadingZeros => 'Same after removing leading zeros',
            self::PrefixMissing => 'Same number, prefix missing on one side',
            self::PrefixDifferent => 'Same number, different prefix',
            self::Similar => 'Similar (two characters swapped)',
            self::Different => 'Different',
            self::Unavailable => 'Not comparable',
        };
    }

    public function polarity(): Polarity
    {
        return match ($this) {
            self::Identical, self::Formatting => Polarity::Agrees,
            self::LeadingZeros, self::PrefixMissing, self::PrefixDifferent, self::Similar => Polarity::Partial,
            self::Different => Polarity::Differs,
            self::Unavailable => Polarity::Info,
        };
    }
}
