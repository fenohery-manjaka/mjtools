<?php

namespace App\Tools\SupplierReconciliation\Review;

use App\Tools\SupplierReconciliation\Domain\Amount;
use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Result\ItemStatus;
use App\Tools\SupplierReconciliation\Result\ReconciliationResult;
use App\Tools\SupplierReconciliation\Result\ResultItem;

/**
 * The engine result with human decisions applied, plus the summary figures
 * shown to the user.
 */
final readonly class ReviewedResult
{
    /**
     * @param  list<ReviewedItem>  $items
     * @param  list<Decision>  $appliedDecisions
     */
    public function __construct(
        public ReconciliationResult $engine,
        public array $items,
        public array $appliedDecisions,
    ) {}

    public function item(string $id): ?ReviewedItem
    {
        foreach ($this->items as $item) {
            if ($item->id === $id) {
                return $item;
            }
        }

        return null;
    }

    public function itemContaining(string $transactionId): ?ReviewedItem
    {
        foreach ($this->items as $item) {
            if (in_array($transactionId, $item->transactionIds(), true)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return list<ReviewedItem>
     */
    public function attentionItems(): array
    {
        return array_values(array_filter($this->items, fn (ReviewedItem $item): bool => $item->needsAttention()));
    }

    /**
     * Transactions not yet matched or excluded, which can be linked manually.
     *
     * @return list<string>
     */
    public function unsettledTransactionIds(Side $side): array
    {
        $ids = [];

        foreach ($this->items as $item) {
            if (! $item->needsAttention()) {
                continue;
            }

            foreach ($side === Side::Statement ? $item->statementIds : $item->ledgerIds as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $engineItems = $this->engine->items;
        $excludedLines = 0;
        $autoLines = 0;
        $autoItems = 0;
        $engineCounts = array_fill_keys(array_map(fn (ItemStatus $s): string => $s->value, ItemStatus::cases()), 0);

        foreach ($engineItems as $item) {
            $engineCounts[$item->status->value]++;

            if ($item->status === ItemStatus::Excluded) {
                $excludedLines += count($item->transactionIds());
            }

            if ($item->status === ItemStatus::Matched) {
                $autoItems++;
                $autoLines += count($item->transactionIds());
            }
        }

        $statementLines = count($this->engine->transactionsOn(Side::Statement));
        $ledgerLines = count($this->engine->transactionsOn(Side::Ledger));
        $analyzed = $statementLines + $ledgerLines - $excludedLines;
        $engineAttention = count(array_filter($engineItems, fn (ResultItem $i): bool => $i->status->needsAttention()));

        $current = array_fill_keys(array_map(fn (ItemStatus $s): string => $s->value, ItemStatus::cases()), 0);
        $resolutions = array_fill_keys(array_map(fn (Resolution $r): string => $r->value, Resolution::cases()), 0);

        foreach ($this->items as $item) {
            $resolutions[$item->resolution->value]++;

            if ($item->needsAttention()) {
                $current[$item->status->value]++;
            }
        }

        return [
            'analyzed_lines' => $analyzed,
            'statement_lines' => $statementLines,
            'ledger_lines' => $ledgerLines,
            'excluded_lines' => $excludedLines,
            'matched_automatically' => ['items' => $autoItems, 'lines' => $autoLines],
            // Share of lines removed from human review — not an accuracy figure (spec §28).
            'cleared_automatically_percent' => $analyzed === 0 ? 0.0 : round($autoLines / $analyzed * 100, 1),
            'engine_attention_items' => $engineAttention,
            'engine_counts' => $engineCounts,
            'attention_items' => count($this->attentionItems()),
            'attention_counts' => $current,
            'resolutions' => $resolutions,
            'amounts' => $this->amounts(),
        ];
    }

    /**
     * Amounts per category of open exceptions, never added together (spec §29).
     *
     * @return array<string, array{count: int, total: string}>
     */
    private function amounts(): array
    {
        $totals = [
            'missing_invoices' => [],
            'missing_credits' => [],
            'amount_differences' => [],
            'ledger_only' => [],
        ];

        foreach ($this->attentionItems() as $item) {
            if ($item->status === ItemStatus::MissingInLedger) {
                foreach ($item->statementIds as $id) {
                    $amount = $this->engine->transaction($id)->amount;

                    if ($amount !== null) {
                        $totals[$amount->isNegative() ? 'missing_credits' : 'missing_invoices'][] = $amount->abs();
                    }
                }
            }

            if ($item->status === ItemStatus::AmountMismatch && $item->difference !== null) {
                $totals['amount_differences'][] = $item->difference->abs();
            }

            if ($item->status === ItemStatus::LedgerOnly) {
                foreach ($item->ledgerIds as $id) {
                    $amount = $this->engine->transaction($id)->amount;

                    if ($amount !== null) {
                        $totals['ledger_only'][] = $amount->abs();
                    }
                }
            }
        }

        return array_map(fn (array $amounts): array => [
            'count' => count($amounts),
            'total' => Amount::sum($amounts)->format(),
        ], $totals);
    }
}
