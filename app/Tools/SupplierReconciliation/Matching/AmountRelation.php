<?php

namespace App\Tools\SupplierReconciliation\Matching;

enum AmountRelation: string
{
    case Equal = 'equal';
    case Different = 'different';
    case OppositeSign = 'opposite_sign';
    case Unavailable = 'unavailable';
}
