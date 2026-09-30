<?php

namespace App\Tools\SupplierReconciliation\Domain;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * A calendar day without time or time zone.
 */
final readonly class CalendarDate
{
    private function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {}

    public static function tryCreate(int $year, int $month, int $day): ?self
    {
        if ($year < 1900 || $year > 2200 || ! checkdate($month, $day, $year)) {
            return null;
        }

        return new self($year, $month, $day);
    }

    public static function fromIso(string $iso): self
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m) !== 1
            || ($date = self::tryCreate((int) $m[1], (int) $m[2], (int) $m[3])) === null) {
            throw new InvalidArgumentException("Invalid ISO date [{$iso}].");
        }

        return $date;
    }

    public function toIso(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }

    /**
     * Number of days since the Unix epoch, used for exact day differences.
     */
    public function dayNumber(): int
    {
        $date = new DateTimeImmutable($this->toIso(), new DateTimeZone('UTC'));

        return intdiv($date->getTimestamp(), 86_400);
    }

    public function daysBetween(self $other): int
    {
        return abs($this->dayNumber() - $other->dayNumber());
    }

    public function isAfter(self $other): bool
    {
        return $this->dayNumber() > $other->dayNumber();
    }

    /**
     * Unambiguous human representation, e.g. "12 Aug 2026".
     */
    public function format(): string
    {
        return (new DateTimeImmutable($this->toIso(), new DateTimeZone('UTC')))->format('j M Y');
    }
}
