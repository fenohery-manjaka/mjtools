<?php

namespace App\Tools\SupplierReconciliation\Matching;

use App\Tools\SupplierReconciliation\Domain\Amount;
use App\Tools\SupplierReconciliation\Domain\Side;
use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Result\Candidate;
use App\Tools\SupplierReconciliation\Result\ItemStatus;
use App\Tools\SupplierReconciliation\Result\MatchKind;
use App\Tools\SupplierReconciliation\Result\Reason;
use App\Tools\SupplierReconciliation\Result\ResultItem;

/**
 * Builds result items with consistent explanations.
 */
final class ItemFactory
{
    /**
     * @param  list<Reason>  $extraReasons  Stage-specific reasons, shown before the field evidence.
     * @param  list<string>  $flags
     */
    public static function pair(
        ItemStatus $status,
        ?MatchKind $kind,
        string $headline,
        PairEvidence $evidence,
        array $extraReasons = [],
        array $flags = [],
    ): ResultItem {
        return new ResultItem(
            id: '',
            status: $status,
            kind: $kind,
            headline: $headline,
            statementIds: [$evidence->statement->id],
            ledgerIds: [$evidence->ledger->id],
            reasons: [...$extraReasons, ...$evidence->reasons()],
            candidates: [$evidence->toCandidate(withReasons: false)],
            difference: $status === ItemStatus::AmountMismatch ? $evidence->difference() : null,
            flags: $flags,
        );
    }

    /**
     * One transaction linked to several on the other side whose amounts add up.
     *
     * @param  list<Transaction>  $group
     * @param  list<Reason>  $extraReasons
     */
    public static function grouped(Transaction $single, array $group, array $extraReasons = []): ResultItem
    {
        $amounts = array_map(fn (Transaction $t): Amount => $t->amount ?? Amount::fromUnits(0), $group);
        $total = Amount::sum($amounts);
        $groupSide = $group[0]->side;
        $addition = implode(' + ', array_map(fn (Amount $a): string => $a->format(), $amounts)).' = '.$total->format();
        $groupIds = array_map(fn (Transaction $t): string => $t->id, $group);

        $reasons = [
            Reason::agrees('group.same_reference', count($group).' '.self::sideNoun($groupSide).' lines share the reference of '.$single->label()),
            Reason::agrees('group.sum', "Together they add up exactly to {$single->amount?->format()} ({$addition})"),
            ...$extraReasons,
            Reason::info('group.not_automatic', 'Combined matches are never validated automatically: please confirm.'),
        ];

        [$statementIds, $ledgerIds] = $groupSide === Side::Ledger
            ? [[$single->id], $groupIds]
            : [$groupIds, [$single->id]];

        return new ResultItem(
            id: '',
            status: ItemStatus::PossibleMatch,
            kind: MatchKind::Grouped,
            headline: $groupSide === Side::Ledger
                ? 'One statement line matches '.count($group).' ledger lines'
                : count($group).' statement lines match one ledger line',
            statementIds: $statementIds,
            ledgerIds: $ledgerIds,
            reasons: $reasons,
            candidates: [new Candidate($statementIds, $ledgerIds, [], [])],
            flags: [$groupSide === Side::Ledger ? ResultItem::FLAG_ONE_TO_MANY : ResultItem::FLAG_MANY_TO_ONE],
        );
    }

    /**
     * @param  list<Reason>  $reasons
     * @param  list<string>  $flags
     */
    public static function single(ItemStatus $status, string $headline, Transaction $transaction, array $reasons, array $flags = []): ResultItem
    {
        return new ResultItem(
            id: '',
            status: $status,
            kind: null,
            headline: $headline,
            statementIds: $transaction->side === Side::Statement ? [$transaction->id] : [],
            ledgerIds: $transaction->side === Side::Ledger ? [$transaction->id] : [],
            reasons: $reasons,
            flags: $flags,
        );
    }

    public static function sideNoun(Side $side): string
    {
        return $side === Side::Statement ? 'statement' : 'ledger';
    }

    /**
     * "rows 4, 9 and 12"
     *
     * @param  list<Transaction>  $transactions
     */
    public static function rowList(array $transactions): string
    {
        $rows = array_map(fn (Transaction $t): string => (string) $t->rowNumber, $transactions);
        $last = array_pop($rows);

        return (count($transactions) > 1 ? 'rows ' : 'row ').($rows === [] ? $last : implode(', ', $rows).' and '.$last);
    }
}
