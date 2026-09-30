<?php

namespace App\Tools\SupplierReconciliation\Matching;

use App\Tools\SupplierReconciliation\Domain\NormalizedReference;

final class ReferenceComparator
{
    /** Minimum significant digits for a prefix-less link ("INV-004583" ↔ "4583"). */
    private const MIN_DIGITS_FOR_PREFIX_LINK = 3;

    /** Minimum key length for the "two characters swapped" relation. */
    private const MIN_LENGTH_FOR_SIMILAR = 5;

    public function compare(?NormalizedReference $a, ?NormalizedReference $b): ReferenceRelation
    {
        if ($a === null || $b === null || ! $a->identifying || ! $b->identifying) {
            return ReferenceRelation::Unavailable;
        }

        if (trim($a->original) === trim($b->original)) {
            return ReferenceRelation::Identical;
        }

        if ($a->typographicKey === $b->typographicKey) {
            return ReferenceRelation::Formatting;
        }

        if ($a->zeroKey === $b->zeroKey) {
            return ReferenceRelation::LeadingZeros;
        }

        if ($a->digitCore !== '' && $a->digitCore === $b->digitCore
            && $a->significantDigits >= self::MIN_DIGITS_FOR_PREFIX_LINK) {
            if ($a->letters === '' || $b->letters === '') {
                return ReferenceRelation::PrefixMissing;
            }

            if ($a->letters !== $b->letters) {
                return ReferenceRelation::PrefixDifferent;
            }
        }

        if ($this->isAdjacentTransposition($a->typographicKey, $b->typographicKey)) {
            return ReferenceRelation::Similar;
        }

        return ReferenceRelation::Different;
    }

    /**
     * True when both keys differ only by two swapped neighbouring characters
     * ("4583" ↔ "4538"). Substitutions are deliberately not accepted:
     * INV-1001 and INV-1002 are usually two different invoices.
     */
    private function isAdjacentTransposition(string $a, string $b): bool
    {
        $length = strlen($a);

        if ($length !== strlen($b) || $length < self::MIN_LENGTH_FOR_SIMILAR || ! mb_check_encoding($a, 'ASCII') || ! mb_check_encoding($b, 'ASCII')) {
            return false;
        }

        $positions = [];

        for ($i = 0; $i < $length; $i++) {
            if ($a[$i] !== $b[$i]) {
                $positions[] = $i;

                if (count($positions) > 2) {
                    return false;
                }
            }
        }

        return count($positions) === 2
            && $positions[1] === $positions[0] + 1
            && $a[$positions[0]] === $b[$positions[1]]
            && $a[$positions[1]] === $b[$positions[0]];
    }
}
