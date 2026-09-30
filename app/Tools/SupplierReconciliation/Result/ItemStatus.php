<?php

namespace App\Tools\SupplierReconciliation\Result;

enum ItemStatus: string
{
    case Matched = 'matched';
    case PossibleMatch = 'possible_match';
    case Ambiguous = 'ambiguous';
    case ReviewRequired = 'review_required';
    case MissingInLedger = 'missing_in_ledger';
    case LedgerOnly = 'ledger_only';
    case AmountMismatch = 'amount_mismatch';
    case DuplicateSuspected = 'duplicate_suspected';
    case Excluded = 'excluded';

    public function label(): string
    {
        return match ($this) {
            self::Matched => 'Matched',
            self::PossibleMatch => 'Possible match',
            self::Ambiguous => 'Ambiguous — review required',
            self::ReviewRequired => 'Review required',
            self::MissingInLedger => 'Missing in ledger',
            self::LedgerOnly => 'Ledger only',
            self::AmountMismatch => 'Amount mismatch',
            self::DuplicateSuspected => 'Duplicate suspected',
            self::Excluded => 'Not a transaction',
        };
    }

    public function needsAttention(): bool
    {
        return $this !== self::Matched && $this !== self::Excluded;
    }
}
