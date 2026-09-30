<?php

namespace App\Tools\SupplierReconciliation\Normalization;

use App\Tools\SupplierReconciliation\Domain\CalendarDate;

final readonly class ParsedDate
{
    private function __construct(
        public ?CalendarDate $date,
        public ?string $error,
    ) {}

    public static function ok(CalendarDate $date): self
    {
        return new self($date, null);
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
        return $this->date === null && $this->error === null;
    }
}
