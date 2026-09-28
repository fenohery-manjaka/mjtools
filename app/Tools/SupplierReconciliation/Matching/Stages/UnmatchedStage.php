<?php

namespace App\Tools\SupplierReconciliation\Matching\Stages;

use App\Tools\SupplierReconciliation\Domain\CalendarDate;
use App\Tools\SupplierReconciliation\Domain\DocumentType;
use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Matching\ItemFactory;
use App\Tools\SupplierReconciliation\Matching\MatchingContext;
use App\Tools\SupplierReconciliation\Matching\PairEvidence;
use App\Tools\SupplierReconciliation\Result\ItemStatus;
use App\Tools\SupplierReconciliation\Result\Reason;
use App\Tools\SupplierReconciliation\Result\ResultItem;

/**
 * Everything still open has no acceptable counterpart: Missing in Ledger or
 * Ledger Only (spec §25). Only observable facts are stated; lines near the end
 * of the other file's period are flagged as possible timing differences (§26).
 */
final class UnmatchedStage implements MatchingStage
{
    public function apply(MatchingContext $context): void
    {
        $latest = [
            Side::Statement->value => $context->latestDate(Side::Statement),
            Side::Ledger->value => $context->latestDate(Side::Ledger),
        ];

        foreach ([Side::Statement, Side::Ledger] as $side) {
            foreach ($context->open($side) as $transaction) {
                $context->resolve($this->itemFor($context, $transaction, $latest[$side->other()->value]));
            }
        }
    }

    private function itemFor(MatchingContext $context, Transaction $t, ?CalendarDate $otherLatest): ResultItem
    {
        $otherNoun = ItemFactory::sideNoun($t->side->other());
        $flags = [];
        $reasons = [$this->mainReason($context, $t, $otherNoun)];

        foreach ($this->relatedElsewhere($context, $t) as $reason) {
            $reasons[] = $reason;
        }

        $type = $t->documentType;

        if ($type === DocumentType::Credit || ($type === DocumentType::Unknown && $t->amount?->isNegative())) {
            $flags[] = ResultItem::FLAG_CREDIT;
        } elseif ($type === DocumentType::Payment) {
            $flags[] = ResultItem::FLAG_PAYMENT;
        }

        if ($t->date !== null && $otherLatest !== null
            && $t->date->dayNumber() > $otherLatest->dayNumber() - $context->policy->timingWindowDays) {
            $flags[] = ResultItem::FLAG_TIMING;
            $reasons[] = Reason::info('unmatched.timing', 'Dated '.$t->date->format().", close to or after the last {$otherNoun} entry (".$otherLatest->format().'): possibly a timing difference rather than an error.');
        }

        if ($t->side === Side::Statement && $t->amount?->isNegative()) {
            $reasons[] = Reason::info('unmatched.credit_risk', 'This reduces the amount owed to the supplier: if it is not recorded, the balance may be overstated.');
        }

        foreach ($t->amountNotes as $note) {
            $reasons[] = Reason::info('amount.convention', ucfirst($t->side->label()).': '.$note);
        }

        return ItemFactory::single(
            status: $t->side === Side::Statement ? ItemStatus::MissingInLedger : ItemStatus::LedgerOnly,
            headline: $this->headline($t, $flags),
            transaction: $t,
            reasons: $reasons,
            flags: $flags,
        );
    }

    private function mainReason(MatchingContext $context, Transaction $t, string $otherNoun): Reason
    {
        $weak = array_values(array_filter(
            $context->allEdgesOf($t->id),
            fn (PairEvidence $e): bool => $e->amountsEqual() && ! $context->isResolved($e->other($t->id)->id),
        ));

        if ($weak !== []) {
            return Reason::differs('unmatched.only_weak_candidates', 'Only weak candidates on the '.$otherNoun.' (same amount, but different references and no close date), not enough to propose a match.');
        }

        return Reason::differs('unmatched.none', 'No '.$otherNoun.' line with a related reference or the same amount and a close date was found.');
    }

    /**
     * Explains lines on the other side that looked related but were used elsewhere.
     *
     * @return list<Reason>
     */
    private function relatedElsewhere(MatchingContext $context, Transaction $t): array
    {
        $reasons = [];

        foreach ($context->allEdgesOf($t->id) as $edge) {
            $other = $edge->other($t->id);
            $item = $context->itemContaining($other->id);

            if ($item === null || ! ($edge->reference->isSameNumber() || $edge->amountsEqual())) {
                continue;
            }

            $what = $edge->reference->isSameNumber()
                ? 'has a related reference'
                : 'has the same amount';

            $reasons[] = Reason::info('unmatched.related_elsewhere', ucfirst($other->label())." {$what} but is already part of another result ({$item->status->label()}).");

            if (count($reasons) === 3) {
                break;
            }
        }

        return $reasons;
    }

    /**
     * @param  list<string>  $flags
     */
    private function headline(Transaction $t, array $flags): string
    {
        $what = match (true) {
            in_array(ResultItem::FLAG_CREDIT, $flags, true) && $t->documentType === DocumentType::Credit => 'Credit',
            in_array(ResultItem::FLAG_CREDIT, $flags, true) => 'Credit or payment',
            in_array(ResultItem::FLAG_PAYMENT, $flags, true) => 'Payment',
            $t->documentType === DocumentType::Invoice => 'Invoice',
            default => 'Transaction',
        };

        return $t->side === Side::Statement ? "{$what} missing in ledger" : "{$what} only in ledger";
    }
}
