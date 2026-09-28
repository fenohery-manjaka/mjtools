<?php

namespace App\Tools\SupplierReconciliation\Normalization;

use App\Tools\SupplierReconciliation\Domain\Amount;

final readonly class ParsedAmount
{
    private function __construct(
        public ?Amount $amount,
        public ?string $error,
    ) {}

    public static function ok(Amount $amount): self
    {
        return new self($amount, null);
    }

    public static function empty(): self
    {
        return new self(null, null);
    }

    public static function error(string $error): self
    {
        return new self(null, $error);
    }

    public function isEmpty(): bool
    {
        return $this->amount === null && $this->error === null;
    }
}
