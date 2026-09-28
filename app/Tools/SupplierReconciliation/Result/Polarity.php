<?php

namespace App\Tools\SupplierReconciliation\Result;

/**
 * Whether a piece of evidence supports, weakens or contradicts a link.
 */
enum Polarity: string
{
    case Agrees = 'agrees';
    case Partial = 'partial';
    case Differs = 'differs';
    case Info = 'info';
}
