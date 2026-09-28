<?php

namespace App\Tools\SupplierReconciliation\Matching\Stages;

use App\Tools\SupplierReconciliation\Matching\MatchingContext;

/**
 * One matching rule. Stages run in a fixed order, from the safest decision to
 * the least certain, and only look at transactions still open.
 */
interface MatchingStage
{
    public function apply(MatchingContext $context): void;
}
