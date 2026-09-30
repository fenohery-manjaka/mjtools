<?php

namespace App\Tools\SupplierReconciliation\Matching;

use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Matching\Stages\AmountMismatchStage;
use App\Tools\SupplierReconciliation\Matching\Stages\CandidateStage;
use App\Tools\SupplierReconciliation\Matching\Stages\CertainMatchStage;
use App\Tools\SupplierReconciliation\Matching\Stages\DuplicateStage;
use App\Tools\SupplierReconciliation\Matching\Stages\GroupMatchStage;
use App\Tools\SupplierReconciliation\Matching\Stages\InsufficientDataStage;
use App\Tools\SupplierReconciliation\Matching\Stages\MatchingStage;
use App\Tools\SupplierReconciliation\Matching\Stages\UnmatchedStage;
use App\Tools\SupplierReconciliation\Result\ReconciliationResult;
use App\Tools\SupplierReconciliation\Result\ResultItem;
use InvalidArgumentException;

/**
 * Deterministic, conservative reconciliation of two lists of canonical
 * transactions. Independent of files, HTTP and UI.
 *
 * Stages run from the safest decision to the least certain; each transaction
 * ends up in exactly one result item.
 */
final class ReconciliationEngine
{
    private readonly ReferenceComparator $comparator;

    public function __construct(
        private readonly MatchingPolicy $policy = new MatchingPolicy,
    ) {
        $this->comparator = new ReferenceComparator;
    }

    /**
     * @param  list<Transaction>  $statement
     * @param  list<Transaction>  $ledger
     */
    public function reconcile(array $statement, array $ledger): ReconciliationResult
    {
        $this->assertSides($statement, Side::Statement);
        $this->assertSides($ledger, Side::Ledger);

        $context = new MatchingContext($statement, $ledger, $this->policy, $this->comparator);

        (new InsufficientDataStage)->apply($context);

        $context->setEdges((new CandidateFinder($this->policy, $this->comparator))->find(
            $context->open(Side::Statement),
            $context->open(Side::Ledger),
        ));

        foreach ($this->stages() as $stage) {
            $stage->apply($context);
        }

        return new ReconciliationResult(
            transactions: [...$statement, ...$ledger],
            items: $this->numbered($context, $context->items()),
            policy: $this->policy->toArray(),
        );
    }

    /**
     * @return list<MatchingStage>
     */
    private function stages(): array
    {
        return [
            new DuplicateStage,
            new CertainMatchStage,
            new GroupMatchStage,
            new AmountMismatchStage,
            new CandidateStage,
            new UnmatchedStage,
        ];
    }

    /**
     * Stable ids in file order: statement rows first, then ledger-only rows.
     *
     * @param  list<ResultItem>  $items
     * @return list<ResultItem>
     */
    private function numbered(MatchingContext $context, array $items): array
    {
        $position = [];

        foreach ([...$context->statement, ...$context->ledger] as $index => $transaction) {
            $position[$transaction->id] = $index;
        }

        usort($items, function (ResultItem $a, ResultItem $b) use ($position): int {
            $first = fn (ResultItem $item): int => min([PHP_INT_MAX, ...array_map(fn (string $id): int => $position[$id], $item->transactionIds())]);

            return $first($a) <=> $first($b);
        });

        return array_map(
            fn (ResultItem $item, int $index): ResultItem => $item->withId('I'.($index + 1)),
            $items,
            array_keys($items),
        );
    }

    /**
     * @param  list<Transaction>  $transactions
     */
    private function assertSides(array $transactions, Side $side): void
    {
        $ids = [];

        foreach ($transactions as $transaction) {
            if ($transaction->side !== $side) {
                throw new InvalidArgumentException("Transaction [{$transaction->id}] is not a {$side->value} transaction.");
            }

            if (isset($ids[$transaction->id])) {
                throw new InvalidArgumentException("Duplicate transaction id [{$transaction->id}].");
            }

            $ids[$transaction->id] = true;
        }
    }
}
