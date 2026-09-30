<?php

namespace App\Tools\SupplierReconciliation\Matching;

use App\Tools\SupplierReconciliation\Domain\Amount;
use App\Tools\SupplierReconciliation\Domain\Transaction;
use App\Tools\SupplierReconciliation\Result\Candidate;
use App\Tools\SupplierReconciliation\Result\FieldComparison;
use App\Tools\SupplierReconciliation\Result\Polarity;
use App\Tools\SupplierReconciliation\Result\Reason;

/**
 * Everything the engine knows about one statement/ledger pair.
 */
final readonly class PairEvidence
{
    public function __construct(
        public Transaction $statement,
        public Transaction $ledger,
        public ReferenceRelation $reference,
        public AmountRelation $amount,
        public ?int $dateDifference,
    ) {}

    public static function between(Transaction $statement, Transaction $ledger, ReferenceComparator $comparator): self
    {
        return new self(
            statement: $statement,
            ledger: $ledger,
            reference: $comparator->compare($statement->reference, $ledger->reference),
            amount: self::compareAmounts($statement->amount, $ledger->amount),
            dateDifference: $statement->date !== null && $ledger->date !== null
                ? $statement->date->daysBetween($ledger->date)
                : null,
        );
    }

    public function key(): string
    {
        return $this->statement->id.'~'.$this->ledger->id;
    }

    public function other(string $transactionId): Transaction
    {
        return $transactionId === $this->statement->id ? $this->ledger : $this->statement;
    }

    public function amountsEqual(): bool
    {
        return $this->amount === AmountRelation::Equal;
    }

    public function difference(): ?Amount
    {
        return $this->statement->amount !== null && $this->ledger->amount !== null
            ? $this->statement->amount->minus($this->ledger->amount)
            : null;
    }

    public function datesWithin(int $days): bool
    {
        return $this->dateDifference !== null && $this->dateDifference <= $days;
    }

    /**
     * Neither date is present because it was left blank or not mapped (as
     * opposed to present but unreadable).
     */
    public function datesAbsent(): bool
    {
        return $this->dateDifference === null
            && ! $this->dateUnreadable($this->statement)
            && ! $this->dateUnreadable($this->ledger);
    }

    /**
     * Internal ranking only — never shown as a probability (spec §33).
     */
    public function score(): int
    {
        $score = (9 - $this->reference->value) * 100;
        $score += match ($this->amount) {
            AmountRelation::Equal => 60,
            AmountRelation::Different => 10,
            default => 0,
        };

        if ($this->dateDifference !== null) {
            $score += max(0, 30 - $this->dateDifference);
        }

        return $score;
    }

    /**
     * @param  bool  $withReasons  False when the item already carries these reasons (single pair).
     */
    public function toCandidate(bool $withReasons = true): Candidate
    {
        return new Candidate(
            statementIds: [$this->statement->id],
            ledgerIds: [$this->ledger->id],
            comparisons: $this->comparisons(),
            reasons: $withReasons ? $this->reasons() : [],
        );
    }

    /**
     * @return list<FieldComparison>
     */
    public function comparisons(): array
    {
        $s = $this->statement;
        $l = $this->ledger;

        return [
            new FieldComparison(
                field: 'reference',
                statementOriginal: $s->original('reference'),
                statementNormalized: $this->comparedReference($s),
                ledgerOriginal: $l->original('reference'),
                ledgerNormalized: $this->comparedReference($l),
                relation: $this->reference->label(),
                polarity: $this->reference->polarity(),
            ),
            new FieldComparison(
                field: 'amount',
                statementOriginal: self::originalAmount($s),
                statementNormalized: $s->amount?->format(),
                ledgerOriginal: self::originalAmount($l),
                ledgerNormalized: $l->amount?->format(),
                relation: $this->amountLabel(),
                polarity: $this->amount === AmountRelation::Equal ? Polarity::Agrees : ($this->amount === AmountRelation::Unavailable ? Polarity::Info : Polarity::Differs),
            ),
            new FieldComparison(
                field: 'date',
                statementOriginal: $s->original('date'),
                statementNormalized: $s->date?->format(),
                ledgerOriginal: $l->original('date'),
                ledgerNormalized: $l->date?->format(),
                relation: $this->dateLabel(),
                polarity: match (true) {
                    $this->dateDifference === 0 => Polarity::Agrees,
                    $this->dateDifference === null => Polarity::Info,
                    default => Polarity::Partial,
                },
            ),
        ];
    }

    /**
     * @return list<Reason>
     */
    public function reasons(): array
    {
        return [
            $this->referenceReason(),
            $this->amountReason(),
            $this->dateReason(),
            ...$this->conventionReasons(),
        ];
    }

    public function referenceReason(): Reason
    {
        $pair = $this->referencePair();

        return match ($this->reference) {
            ReferenceRelation::Identical => Reason::agrees('reference.identical', 'Same reference ('.trim((string) $this->statement->reference?->original).')'),
            ReferenceRelation::Formatting => Reason::agrees('reference.formatting', "Same reference once formatting is ignored ({$pair})"),
            ReferenceRelation::LeadingZeros => Reason::partial('reference.leading_zeros', "Same reference once leading zeros are removed ({$pair})"),
            ReferenceRelation::PrefixMissing => Reason::partial('reference.prefix_missing', "Similar reference, prefix missing on one side ({$pair})"),
            ReferenceRelation::PrefixDifferent => Reason::partial('reference.prefix_different', "Same number but different prefix ({$pair})"),
            ReferenceRelation::Similar => Reason::partial('reference.similar', "Similar reference, two characters swapped ({$pair})"),
            ReferenceRelation::Different => Reason::differs('reference.different', "Different references ({$pair})"),
            ReferenceRelation::Unavailable => Reason::info('reference.unavailable', $this->unavailableReferenceMessage()),
        };
    }

    public function amountReason(): Reason
    {
        $s = $this->statement->amount;
        $l = $this->ledger->amount;

        if ($s === null || $l === null) {
            return Reason::differs('amount.unavailable', 'Amount not available on both sides');
        }

        return match ($this->amount) {
            AmountRelation::Equal => Reason::agrees('amount.equal', "Same amount ({$s->format()})"),
            AmountRelation::OppositeSign => Reason::differs('amount.opposite_sign', "Same amount but opposite sign (statement {$s->format()}, ledger {$l->format()})"),
            default => Reason::differs('amount.different', "Amounts differ: statement {$s->format()}, ledger {$l->format()} (difference {$s->minus($l)->format()})"),
        };
    }

    public function dateReason(): Reason
    {
        if ($this->dateDifference === null) {
            foreach ([$this->statement, $this->ledger] as $transaction) {
                if ($this->dateUnreadable($transaction)) {
                    return Reason::info('date.unreadable', 'Date could not be read on '.$transaction->rowLabel().' ("'.$transaction->original('date').'")');
                }
            }

            return Reason::info('date.unavailable', 'Dates not available on both sides');
        }

        $dates = $this->statement->date?->format().' vs '.$this->ledger->date?->format();

        return match (true) {
            $this->dateDifference === 0 => Reason::agrees('date.same', 'Same date ('.$this->statement->date?->format().')'),
            default => Reason::partial('date.difference', 'Date difference: '.$this->dateDifference.' '.($this->dateDifference === 1 ? 'day' : 'days')." ({$dates})"),
        };
    }

    /**
     * @return list<Reason>
     */
    private function conventionReasons(): array
    {
        $reasons = [];

        foreach ([$this->statement, $this->ledger] as $transaction) {
            foreach ($transaction->amountNotes as $note) {
                $reasons[] = Reason::info('amount.convention', ucfirst($transaction->side->label()).': '.$note);
            }
        }

        return array_values(array_unique($reasons, SORT_REGULAR));
    }

    /**
     * The form of the reference actually compared for this relation
     * ("INV-0004583" is compared as "4583" when the prefix is ignored).
     */
    public function comparedReference(Transaction $transaction): ?string
    {
        $reference = $transaction->reference;

        if ($reference === null) {
            return null;
        }

        return match ($this->reference) {
            ReferenceRelation::Identical => trim($reference->original),
            ReferenceRelation::LeadingZeros => str_replace('|', '', $reference->zeroKey),
            ReferenceRelation::PrefixMissing, ReferenceRelation::PrefixDifferent => str_replace('|', '-', $reference->digitCore),
            default => $reference->typographicKey,
        };
    }

    private function referencePair(): string
    {
        return trim((string) $this->statement->reference?->original).' ↔ '.trim((string) $this->ledger->reference?->original);
    }

    private function unavailableReferenceMessage(): string
    {
        foreach ([$this->statement, $this->ledger] as $transaction) {
            if ($transaction->reference === null) {
                return 'No reference on '.$transaction->rowLabel();
            }

            if (! $transaction->reference->identifying) {
                return 'Reference "'.trim($transaction->reference->original).'" on '.$transaction->rowLabel().' does not identify a document';
            }
        }

        return 'References cannot be compared';
    }

    private function amountLabel(): string
    {
        return match ($this->amount) {
            AmountRelation::Equal => 'Equal',
            AmountRelation::OppositeSign => 'Opposite sign',
            AmountRelation::Different => 'Differs by '.$this->difference()?->format(),
            AmountRelation::Unavailable => 'Not comparable',
        };
    }

    private function dateLabel(): string
    {
        return match (true) {
            $this->dateDifference === null => 'Not comparable',
            $this->dateDifference === 0 => 'Same day',
            $this->dateDifference === 1 => '1 day apart',
            default => $this->dateDifference.' days apart',
        };
    }

    private function dateUnreadable(Transaction $transaction): bool
    {
        return $transaction->date === null && $transaction->original('date') !== null;
    }

    private static function compareAmounts(?Amount $statement, ?Amount $ledger): AmountRelation
    {
        return match (true) {
            $statement === null || $ledger === null => AmountRelation::Unavailable,
            $statement->equals($ledger) => AmountRelation::Equal,
            ! $statement->isZero() && $statement->equals($ledger->negate()) => AmountRelation::OppositeSign,
            default => AmountRelation::Different,
        };
    }

    private static function originalAmount(Transaction $transaction): ?string
    {
        $amount = $transaction->original('amount');

        if ($amount !== null) {
            return $amount;
        }

        $debit = $transaction->original('debit');
        $credit = $transaction->original('credit');

        return match (true) {
            $debit !== null && $credit !== null => "Debit {$debit} / Credit {$credit}",
            $debit !== null => "Debit {$debit}",
            $credit !== null => "Credit {$credit}",
            default => null,
        };
    }
}
