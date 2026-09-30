<?php

namespace App\Tools\SupplierReconciliation\Domain;

/**
 * A document reference in several comparison forms, always keeping the original.
 *
 * - typographicKey: upper case, letters and digits only ("INV-004583" -> "INV004583")
 * - zeroKey: letter/digit runs with leading zeros removed ("INV-004583" -> "INV|4583")
 * - letters: letter runs only ("INV")
 * - digitCore: digit runs without leading zeros ("4583")
 */
final readonly class NormalizedReference
{
    /**
     * @param  list<string>  $steps  Human description of the formatting differences ignored.
     */
    public function __construct(
        public string $original,
        public string $typographicKey,
        public string $zeroKey,
        public string $letters,
        public string $digitCore,
        public int $significantDigits,
        public bool $identifying,
        public array $steps,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'original' => $this->original,
            'typographic_key' => $this->typographicKey,
            'zero_key' => $this->zeroKey,
            'letters' => $this->letters,
            'digit_core' => $this->digitCore,
            'significant_digits' => $this->significantDigits,
            'identifying' => $this->identifying,
            'steps' => $this->steps,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            original: (string) $data['original'],
            typographicKey: (string) $data['typographic_key'],
            zeroKey: (string) $data['zero_key'],
            letters: (string) $data['letters'],
            digitCore: (string) $data['digit_core'],
            significantDigits: (int) $data['significant_digits'],
            identifying: (bool) $data['identifying'],
            steps: array_values(array_map('strval', (array) $data['steps'])),
        );
    }
}
