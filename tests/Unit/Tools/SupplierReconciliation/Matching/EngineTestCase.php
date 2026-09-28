<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Matching;

use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Matching\MatchingPolicy;
use App\Tools\SupplierReconciliation\Matching\ReconciliationEngine;
use App\Tools\SupplierReconciliation\Result\ItemStatus;
use App\Tools\SupplierReconciliation\Result\ReconciliationResult;
use App\Tools\SupplierReconciliation\Result\ResultItem;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Tools\SupplierReconciliation\Support\Transactions;

abstract class EngineTestCase extends TestCase
{
    /**
     * @param  list<array{0: ?string, 1: ?string, 2: ?string, 3?: ?string}>  $statement
     * @param  list<array{0: ?string, 1: ?string, 2: ?string, 3?: ?string}>  $ledger
     */
    protected function reconcile(array $statement, array $ledger, ?MatchingPolicy $policy = null): ReconciliationResult
    {
        return $this->reconcileTransactions(Transactions::statement($statement), Transactions::ledger($ledger), $policy);
    }

    /**
     * @param  list<Transaction>  $statement
     * @param  list<Transaction>  $ledger
     */
    protected function reconcileTransactions(array $statement, array $ledger, ?MatchingPolicy $policy = null): ReconciliationResult
    {
        $result = (new ReconciliationEngine($policy ?? new MatchingPolicy))->reconcile($statement, $ledger);

        $this->assertEveryTransactionInExactlyOneItem($result);

        return $result;
    }

    protected function itemOf(ReconciliationResult $result, string $transactionId): ResultItem
    {
        $itemId = $result->itemIdFor($transactionId);
        $this->assertNotNull($itemId, "Transaction {$transactionId} has no item.");

        $item = $result->item($itemId);
        $this->assertNotNull($item);

        return $item;
    }

    protected function assertStatus(ItemStatus $expected, ReconciliationResult $result, string $transactionId): ResultItem
    {
        $item = $this->itemOf($result, $transactionId);

        $this->assertSame(
            $expected,
            $item->status,
            "{$transactionId}: expected {$expected->value}, got {$item->status->value} ({$item->headline}).\n".$this->describe($item),
        );

        return $item;
    }

    /**
     * @param  list<string>  $statementIds
     * @param  list<string>  $ledgerIds
     */
    protected function assertLinked(ReconciliationResult $result, array $statementIds, array $ledgerIds): ResultItem
    {
        $item = $this->itemOf($result, $statementIds[0] ?? $ledgerIds[0]);

        $this->assertEqualsCanonicalizing($statementIds, $item->statementIds, $this->describe($item));
        $this->assertEqualsCanonicalizing($ledgerIds, $item->ledgerIds, $this->describe($item));

        return $item;
    }

    protected function assertReason(ResultItem $item, string $code): void
    {
        $codes = array_map(fn ($reason) => $reason->code, $item->reasons);

        $this->assertContains($code, $codes, "Missing reason {$code}.\n".$this->describe($item));
    }

    protected function describe(ResultItem $item): string
    {
        $lines = ["[{$item->status->value}] {$item->headline} S=".implode(',', $item->statementIds).' L='.implode(',', $item->ledgerIds)];

        foreach ($item->reasons as $reason) {
            $lines[] = "  - ({$reason->code}) {$reason->message}";
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    protected function autoMatchedPairs(ReconciliationResult $result): array
    {
        $pairs = [];

        foreach ($result->items as $item) {
            if ($item->status === ItemStatus::Matched) {
                $pairs[] = implode('+', $item->statementIds).'='.implode('+', $item->ledgerIds);
            }
        }

        sort($pairs);

        return $pairs;
    }

    private function assertEveryTransactionInExactlyOneItem(ReconciliationResult $result): void
    {
        $seen = [];

        foreach ($result->items as $item) {
            foreach ($item->transactionIds() as $id) {
                $this->assertArrayNotHasKey($id, $seen, "{$id} appears in several items.");
                $seen[$id] = true;
            }
        }

        $this->assertEqualsCanonicalizing(array_keys($result->transactions), array_keys($seen), 'Every transaction must appear in exactly one item.');
    }
}
