<?php

namespace App\Tools\SupplierReconciliation\Matching;

use App\Tools\SupplierReconciliation\Domain\CalendarDate;
use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Result\ResultItem;
use LogicException;

/**
 * Working state shared by the matching stages: which transactions are still
 * open, the candidate pairs, and the items produced so far.
 */
final class MatchingContext
{
    /** @var array<string, Transaction> */
    private array $transactions = [];

    /** @var array<string, true> */
    private array $resolved = [];

    /** @var list<ResultItem> */
    private array $items = [];

    /** @var array<string, list<PairEvidence>> */
    private array $edgesByTransaction = [];

    /** @var list<PairEvidence> */
    private array $edges = [];

    /**
     * @param  list<Transaction>  $statement
     * @param  list<Transaction>  $ledger
     */
    public function __construct(
        public readonly array $statement,
        public readonly array $ledger,
        public readonly MatchingPolicy $policy,
        public readonly ReferenceComparator $comparator,
    ) {
        foreach ([...$statement, ...$ledger] as $transaction) {
            $this->transactions[$transaction->id] = $transaction;
        }
    }

    public function transaction(string $id): Transaction
    {
        return $this->transactions[$id] ?? throw new LogicException("Unknown transaction [{$id}].");
    }

    public function isResolved(string $id): bool
    {
        return isset($this->resolved[$id]);
    }

    /**
     * @return list<Transaction>
     */
    public function open(Side $side): array
    {
        $transactions = $side === Side::Statement ? $this->statement : $this->ledger;

        return array_values(array_filter($transactions, fn (Transaction $t): bool => ! isset($this->resolved[$t->id])));
    }

    /**
     * @param  list<PairEvidence>  $edges
     */
    public function setEdges(array $edges): void
    {
        $this->edges = $edges;
        $this->edgesByTransaction = [];

        foreach ($edges as $edge) {
            $this->edgesByTransaction[$edge->statement->id][] = $edge;
            $this->edgesByTransaction[$edge->ledger->id][] = $edge;
        }
    }

    /**
     * Candidate pairs of a transaction whose counterpart is still open.
     *
     * @return list<PairEvidence>
     */
    public function openEdgesOf(string $id): array
    {
        return array_values(array_filter(
            $this->edgesByTransaction[$id] ?? [],
            fn (PairEvidence $e): bool => ! isset($this->resolved[$e->other($id)->id]),
        ));
    }

    /**
     * All candidate pairs of a transaction, including resolved counterparts.
     *
     * @return list<PairEvidence>
     */
    public function allEdgesOf(string $id): array
    {
        return $this->edgesByTransaction[$id] ?? [];
    }

    /**
     * Candidate pairs where both transactions are still open.
     *
     * @return list<PairEvidence>
     */
    public function openEdges(): array
    {
        return array_values(array_filter(
            $this->edges,
            fn (PairEvidence $e): bool => ! isset($this->resolved[$e->statement->id]) && ! isset($this->resolved[$e->ledger->id]),
        ));
    }

    public function resolve(ResultItem $item): void
    {
        foreach ($item->transactionIds() as $id) {
            if (isset($this->resolved[$id])) {
                throw new LogicException("Transaction [{$id}] is already resolved.");
            }
        }

        foreach ($item->transactionIds() as $id) {
            $this->resolved[$id] = true;
        }

        $this->items[] = $item;
    }

    /**
     * @return list<ResultItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function itemContaining(string $transactionId): ?ResultItem
    {
        foreach ($this->items as $item) {
            if (in_array($transactionId, $item->transactionIds(), true)) {
                return $item;
            }
        }

        return null;
    }

    public function latestDate(Side $side): ?CalendarDate
    {
        $latest = null;

        foreach ($side === Side::Statement ? $this->statement : $this->ledger as $transaction) {
            if ($transaction->date !== null && ! $transaction->isBalanceLine
                && ($latest === null || $transaction->date->isAfter($latest))) {
                $latest = $transaction->date;
            }
        }

        return $latest;
    }
}
