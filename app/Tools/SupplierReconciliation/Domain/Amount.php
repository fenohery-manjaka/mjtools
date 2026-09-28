<?php

namespace App\Tools\SupplierReconciliation\Domain;

use InvalidArgumentException;

/**
 * Exact monetary amount stored as an integer number of ten-thousandths.
 *
 * Floats are never used, so comparisons are exact.
 */
final readonly class Amount
{
    public const SCALE = 10_000;

    private function __construct(public int $units) {}

    public static function fromUnits(int $units): self
    {
        return new self($units);
    }

    /**
     * Build from a canonical decimal string such as "-1240.5" (dot decimal, no grouping).
     */
    public static function fromDecimal(string $decimal): self
    {
        if (preg_match('/^(-)?(\d+)(?:\.(\d{1,4}))?$/', $decimal, $m) !== 1) {
            throw new InvalidArgumentException("Invalid canonical decimal [{$decimal}].");
        }

        $units = ((int) $m[2]) * self::SCALE + (int) str_pad($m[3] ?? '', 4, '0');

        return new self($m[1] === '-' ? -$units : $units);
    }

    public function equals(self $other): bool
    {
        return $this->units === $other->units;
    }

    public function isNegative(): bool
    {
        return $this->units < 0;
    }

    public function isPositive(): bool
    {
        return $this->units > 0;
    }

    public function isZero(): bool
    {
        return $this->units === 0;
    }

    public function negate(): self
    {
        return new self(-$this->units);
    }

    public function abs(): self
    {
        return new self(abs($this->units));
    }

    public function plus(self $other): self
    {
        return new self($this->units + $other->units);
    }

    public function minus(self $other): self
    {
        return new self($this->units - $other->units);
    }

    public function hasSameSignAs(self $other): bool
    {
        return ($this->units <=> 0) === ($other->units <=> 0);
    }

    /**
     * Canonical machine representation, e.g. "-1240.50".
     */
    public function toDecimal(): string
    {
        $abs = abs($this->units);
        $integer = intdiv($abs, self::SCALE);
        $fraction = str_pad((string) ($abs % self::SCALE), 4, '0', STR_PAD_LEFT);
        $fraction = rtrim(substr($fraction, 2), '0') === ''
            ? substr($fraction, 0, 2)
            : rtrim($fraction, '0');

        return ($this->units < 0 ? '-' : '').$integer.'.'.$fraction;
    }

    /**
     * Human representation with thousands separators, e.g. "-1,240.50".
     */
    public function format(): string
    {
        [$integer, $fraction] = explode('.', ltrim($this->toDecimal(), '-'));

        return ($this->units < 0 ? '-' : '').number_format((int) $integer).'.'.$fraction;
    }

    /**
     * @param  list<self>  $amounts
     */
    public static function sum(array $amounts): self
    {
        return new self(array_sum(array_map(fn (self $a): int => $a->units, $amounts)));
    }
}
