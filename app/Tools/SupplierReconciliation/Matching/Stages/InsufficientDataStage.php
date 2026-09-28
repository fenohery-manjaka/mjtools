<?php

namespace App\Tools\SupplierReconciliation\Matching\Stages;

use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Matching\MatchingContext;
use App\Tools\SupplierReconciliation\Result\ItemStatus;
use App\Tools\SupplierReconciliation\Result\Reason;
use App\Tools\SupplierReconciliation\Result\ResultItem;

/**
 * Sets aside balance/total lines and rows that cannot be compared reliably,
 * instead of producing an artificial result (spec §10).
 */
final class InsufficientDataStage implements MatchingStage
{
    public function apply(MatchingContext $context): void
    {
        foreach ([...$context->open(Side::Statement), ...$context->open(Side::Ledger)] as $transaction) {
            $item = $this->itemFor($transaction);

            if ($item !== null) {
                $context->resolve($item);
            }
        }
    }

    private function itemFor(Transaction $t): ?ResultItem
    {
        [$statementIds, $ledgerIds] = $t->side === Side::Statement ? [[$t->id], []] : [[], [$t->id]];

        if ($t->isBalanceLine) {
            return new ResultItem(
                id: '',
                status: ItemStatus::Excluded,
                kind: null,
                headline: 'Balance or total line',
                statementIds: $statementIds,
                ledgerIds: $ledgerIds,
                reasons: [Reason::info('excluded.balance_line', 'This line describes a balance or total, not a transaction, so it is not reconciled.')],
            );
        }

        if ($t->amount === null) {
            $reasons = $t->original('amount') === null && $t->original('debit') === null && $t->original('credit') === null
                ? [Reason::differs('data.no_amount', 'No amount on this line.')]
                : [Reason::differs('data.unreadable_amount', 'The amount could not be interpreted.')];

            foreach ($t->issues as $issue) {
                $reasons[] = Reason::info('data.issue', $issue);
            }

            return new ResultItem(
                id: '',
                status: ItemStatus::ReviewRequired,
                kind: null,
                headline: 'Amount missing or unreadable',
                statementIds: $statementIds,
                ledgerIds: $ledgerIds,
                reasons: $reasons,
            );
        }

        if (! $t->hasIdentifyingReference() && $t->date === null) {
            $reasons = [Reason::differs('data.no_reference_no_date', 'Neither a usable reference nor a valid date: this line cannot be compared reliably.')];

            foreach ($t->issues as $issue) {
                $reasons[] = Reason::info('data.issue', $issue);
            }

            return new ResultItem(
                id: '',
                status: ItemStatus::ReviewRequired,
                kind: null,
                headline: 'Not enough information to compare',
                statementIds: $statementIds,
                ledgerIds: $ledgerIds,
                reasons: $reasons,
            );
        }

        return null;
    }
}
