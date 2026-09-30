<?php

namespace App\Tools\SupplierReconciliation\Mapping;

enum Field: string
{
    case Reference = 'reference';
    case Date = 'date';
    case Amount = 'amount';
    case Debit = 'debit';
    case Credit = 'credit';
    case Type = 'type';
    case Description = 'description';
    case Supplier = 'supplier';

    public function label(): string
    {
        return match ($this) {
            self::Reference => 'Reference',
            self::Date => 'Date',
            self::Amount => 'Amount',
            self::Debit => 'Debit',
            self::Credit => 'Credit',
            self::Type => 'Type',
            self::Description => 'Description',
            self::Supplier => 'Supplier',
        };
    }

    public function help(): string
    {
        return match ($this) {
            self::Reference => 'Invoice or document number',
            self::Date => 'Transaction, invoice or posting date',
            self::Amount => 'Signed amount (one column)',
            self::Debit => 'Debit column',
            self::Credit => 'Credit column',
            self::Type => 'Optional: invoice, credit note, payment…',
            self::Description => 'Optional: shown for context only',
            self::Supplier => 'Optional: lets you keep only one supplier',
        };
    }
}
