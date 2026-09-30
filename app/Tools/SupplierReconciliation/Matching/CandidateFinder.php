<?php

namespace App\Tools\SupplierReconciliation\Matching;

use App\Tools\SupplierReconciliation\Domain\Transaction;

/**
 * Finds every statement/ledger pair worth considering, using indexes rather
 * than comparing every pair:
 *
 * - pairs whose references are related (any amount), found through reference keys;
 * - pairs with exactly the same amount, found through an amount index, kept
 *   when their references are related or their dates are close.
 */
final class CandidateFinder
{
    public function __construct(
        private readonly MatchingPolicy $policy,
        private readonly ReferenceComparator $comparator,
    ) {}

    /**
     * @param  list<Transaction>  $statement
     * @param  list<Transaction>  $ledger
     * @return list<PairEvidence> ordered by statement position, then ledger position
     */
    public function find(array $statement, array $ledger): array
    {
        $ledgerPosition = array_flip(array_map(fn (Transaction $t): string => $t->id, $ledger));
        $referenceIndex = $this->referenceIndex($ledger);
        $amountIndex = [];

        foreach ($ledger as $transaction) {
            if ($transaction->amount !== null) {
                $amountIndex[$transaction->amount->units][] = $transaction;
            }
        }

        $statementByAmount = [];

        foreach ($statement as $transaction) {
            if ($transaction->amount !== null) {
                $statementByAmount[$transaction->amount->units][] = $transaction->id;
            }
        }

        $maxDateWindow = max($this->policy->amountOnlyMaxDateDays, $this->policy->differentReferenceMaxDateDays);
        $edges = [];

        foreach ($statement as $s) {
            $pairs = [];

            foreach ($this->referenceKeys($s) as $key) {
                foreach ($referenceIndex[$key] ?? [] as $l) {
                    $pairs[$l->id] = $l;
                }
            }

            foreach ($pairs as $l) {
                $evidence = PairEvidence::between($s, $l, $this->comparator);

                if ($evidence->reference->isRelated()) {
                    $edges[$evidence->key()] = $evidence;
                }
            }

            if ($s->amount === null) {
                continue;
            }

            $bucket = $amountIndex[$s->amount->units] ?? [];
            $bucketPairs = count($bucket) * count($statementByAmount[$s->amount->units]);

            foreach ($bucket as $l) {
                if (isset($edges[$s->id.'~'.$l->id])) {
                    continue;
                }

                $evidence = PairEvidence::between($s, $l, $this->comparator);

                if ($evidence->reference->isRelated()
                    || ($bucketPairs <= $this->policy->maxAmountBucketPairs && $evidence->datesWithin($maxDateWindow))) {
                    $edges[$evidence->key()] = $evidence;
                }
            }
        }

        $statementPosition = array_flip(array_map(fn (Transaction $t): string => $t->id, $statement));
        $edges = array_values($edges);

        usort($edges, fn (PairEvidence $a, PairEvidence $b): int => [$statementPosition[$a->statement->id], $ledgerPosition[$a->ledger->id]]
            <=> [$statementPosition[$b->statement->id], $ledgerPosition[$b->ledger->id]]);

        return $edges;
    }

    /**
     * @param  list<Transaction>  $transactions
     * @return array<string, list<Transaction>>
     */
    private function referenceIndex(array $transactions): array
    {
        $index = [];

        foreach ($transactions as $transaction) {
            foreach ($this->referenceKeys($transaction) as $key) {
                $index[$key][] = $transaction;
            }
        }

        return $index;
    }

    /**
     * @return list<string>
     */
    private function referenceKeys(Transaction $transaction): array
    {
        $reference = $transaction->reference;

        if ($reference === null || ! $reference->identifying) {
            return [];
        }

        $keys = ['t:'.$reference->typographicKey, 'z:'.$reference->zeroKey];

        // Short numbers ("INV-1") would link unrelated documents by their digits alone.
        if ($reference->significantDigits >= 3) {
            $keys[] = 'd:'.$reference->digitCore;
        }

        return $keys;
    }
}
