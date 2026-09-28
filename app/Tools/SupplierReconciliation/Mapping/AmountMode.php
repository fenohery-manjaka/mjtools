<?php

namespace App\Tools\SupplierReconciliation\Mapping;

enum AmountMode: string
{
    case Signed = 'signed';
    case DebitCredit = 'debit_credit';
}
