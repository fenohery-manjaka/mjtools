<?php

namespace App\Tools\SupplierReconciliation\Domain;

/**
 * Nature of a transaction as far as it can be told from the data.
 */
enum DocumentType: string
{
    case Invoice = 'invoice';
    case Credit = 'credit';
    case Payment = 'payment';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Invoice',
            self::Credit => 'Credit note',
            self::Payment => 'Payment',
            self::Unknown => 'Transaction',
        };
    }

    /**
     * Whether this type reduces the balance owed to the supplier.
     */
    public function reducesBalance(): bool
    {
        return $this === self::Credit || $this === self::Payment;
    }
}
