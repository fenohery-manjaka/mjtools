<?php

namespace App\Tools\SupplierReconciliation\Runs;

use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Result\ReconciliationResult;
use App\Tools\SupplierReconciliation\Result\ResultItem;
use JsonException;

/**
 * Stores an engine result as JSON lines: a header, then one transaction or
 * item per line. Decoding hydrates line by line, so a large result never
 * exists as one huge PHP array in memory.
 */
final class ResultCodec
{
    public function encode(ReconciliationResult $result): string
    {
        $lines = [json_encode([
            'engine_version' => ReconciliationResult::ENGINE_VERSION,
            'policy' => $result->policy,
            'transactions' => count($result->transactions),
            'items' => count($result->items),
        ], JSON_THROW_ON_ERROR)];

        foreach ($result->transactions as $transaction) {
            $lines[] = json_encode($transaction->toArray(), JSON_THROW_ON_ERROR);
        }

        foreach ($result->items as $item) {
            $lines[] = json_encode($item->toArray(), JSON_THROW_ON_ERROR);
        }

        return implode("\n", $lines);
    }

    /**
     * @throws JsonException
     */
    public function decode(string $encoded): ReconciliationResult
    {
        $offset = 0;
        $header = $this->line($encoded, $offset);
        $transactions = [];
        $items = [];

        for ($i = 0; $i < (int) $header['transactions']; $i++) {
            $transactions[] = Transaction::fromArray($this->line($encoded, $offset));
        }

        for ($i = 0; $i < (int) $header['items']; $i++) {
            $items[] = ResultItem::fromArray($this->line($encoded, $offset));
        }

        /** @var array<string, int|string> $policy */
        $policy = (array) $header['policy'];

        return new ReconciliationResult($transactions, $items, $policy);
    }

    /**
     * @return array<string, mixed>
     */
    private function line(string $encoded, int &$offset): array
    {
        $end = strpos($encoded, "\n", $offset);
        $json = $end === false ? substr($encoded, $offset) : substr($encoded, $offset, $end - $offset);
        $offset = $end === false ? strlen($encoded) : $end + 1;

        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $data;
    }
}
