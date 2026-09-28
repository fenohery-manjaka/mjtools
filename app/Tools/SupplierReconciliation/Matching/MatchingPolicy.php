<?php

namespace App\Tools\SupplierReconciliation\Matching;

/**
 * Thresholds of the matching engine. Defaults are deliberately prudent
 * (spec §17: to be calibrated against test data).
 */
final readonly class MatchingPolicy
{
    public function __construct(
        /** Max date gap for an automatic match on a reference identical after formatting. */
        public int $certainMaxDateDays = 14,
        /** Max date gap for an automatic match when leading zeros had to be removed. */
        public int $leadingZerosMaxDateDays = 7,
        /** Max date gap for proposing a same-amount candidate when no reference is usable. */
        public int $amountOnlyMaxDateDays = 7,
        /** Max date gap for proposing a same-amount candidate whose references differ. */
        public int $differentReferenceMaxDateDays = 3,
        /** Lines dated this close to the other file's last entry are flagged as possible timing differences. */
        public int $timingWindowDays = 5,
        /** Maximum number of lines combined in a one-to-many / many-to-one proposal. */
        public int $maxGroupSize = 5,
        /** Above this many pairs in one same-amount bucket, amount-only candidates are not generated. */
        public int $maxAmountBucketPairs = 50_000,
    ) {}

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
