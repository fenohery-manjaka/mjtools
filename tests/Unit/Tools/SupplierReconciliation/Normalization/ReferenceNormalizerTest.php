<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Normalization;

use App\Tools\SupplierReconciliation\Normalization\ReferenceNormalizer;
use PHPUnit\Framework\TestCase;

class ReferenceNormalizerTest extends TestCase
{
    public function test_it_keeps_the_original_value(): void
    {
        $reference = (new ReferenceNormalizer)->normalize(' INV-004583 ');

        $this->assertNotNull($reference);
        $this->assertSame(' INV-004583 ', $reference->original);
        $this->assertSame('INV004583', $reference->typographicKey);
    }

    public function test_typographic_differences_share_the_same_key(): void
    {
        $normalizer = new ReferenceNormalizer;

        $keys = array_map(
            fn (string $raw): ?string => $normalizer->normalize($raw)?->typographicKey,
            ['INV-123', 'INV123', 'inv-123', 'INV 123', ' inv/123 ', 'INV.123', 'INV_123'],
        );

        $this->assertSame(['INV123'], array_values(array_unique($keys)));
    }

    public function test_leading_zeros_are_only_removed_in_the_zero_key(): void
    {
        $normalizer = new ReferenceNormalizer;
        $padded = $normalizer->normalize('INV-000123');
        $plain = $normalizer->normalize('INV-123');

        $this->assertNotNull($padded);
        $this->assertNotNull($plain);
        $this->assertNotSame($padded->typographicKey, $plain->typographicKey);
        $this->assertSame($padded->zeroKey, $plain->zeroKey);
        $this->assertSame('INV|123', $plain->zeroKey);
    }

    public function test_it_splits_letters_and_digits(): void
    {
        $reference = (new ReferenceNormalizer)->normalize('INV-2024-0012');

        $this->assertNotNull($reference);
        $this->assertSame('INV', $reference->letters);
        $this->assertSame('2024|12', $reference->digitCore);
        $this->assertSame(6, $reference->significantDigits);
    }

    public function test_references_without_digits_do_not_identify_a_document(): void
    {
        $normalizer = new ReferenceNormalizer;

        $this->assertFalse($normalizer->normalize('PAYMENT')?->identifying);
        $this->assertFalse($normalizer->normalize('12')?->identifying);
        $this->assertTrue($normalizer->normalize('INV-1')?->identifying);
        $this->assertTrue($normalizer->normalize('4583')?->identifying);
    }

    public function test_empty_or_punctuation_only_references_are_absent(): void
    {
        $normalizer = new ReferenceNormalizer;

        $this->assertNull($normalizer->normalize(null));
        $this->assertNull($normalizer->normalize('  '));
        $this->assertNull($normalizer->normalize('--'));
    }

    public function test_it_describes_ignored_formatting(): void
    {
        $reference = (new ReferenceNormalizer)->normalize(' inv-123');

        $this->assertNotNull($reference);
        $this->assertSame([
            'surrounding or repeated spaces ignored',
            'letter case ignored',
            'spaces, separators and punctuation ignored',
        ], $reference->steps);
    }
}
