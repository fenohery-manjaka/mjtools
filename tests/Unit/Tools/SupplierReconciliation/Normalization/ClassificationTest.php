<?php

namespace Tests\Unit\Tools\SupplierReconciliation\Normalization;

use App\Tools\SupplierReconciliation\Domain\Amount;
use App\Tools\SupplierReconciliation\Domain\DocumentType;
use App\Tools\SupplierReconciliation\Normalization\BalanceLineDetector;
use App\Tools\SupplierReconciliation\Normalization\DocumentTypeClassifier;
use App\Tools\SupplierReconciliation\Normalization\ReferenceNormalizer;
use PHPUnit\Framework\TestCase;

class ClassificationTest extends TestCase
{
    public function test_type_column_wins(): void
    {
        $classifier = new DocumentTypeClassifier;
        $reference = (new ReferenceNormalizer)->normalize('INV-100');

        $this->assertSame(DocumentType::Credit, $classifier->classify('Credit Note', $reference, Amount::fromDecimal('100')));
        $this->assertSame(DocumentType::Payment, $classifier->classify('Paiement reçu', null, Amount::fromDecimal('-100')));
        $this->assertSame(DocumentType::Invoice, $classifier->classify('Facture', null, Amount::fromDecimal('100')));
        $this->assertSame(DocumentType::Credit, $classifier->classify('Avoir', null, Amount::fromDecimal('-100')));
    }

    public function test_reference_prefix_is_used_without_type_column(): void
    {
        $classifier = new DocumentTypeClassifier;
        $normalizer = new ReferenceNormalizer;

        $this->assertSame(DocumentType::Credit, $classifier->classify(null, $normalizer->normalize('CN-00824'), Amount::fromDecimal('-420')));
        $this->assertSame(DocumentType::Payment, $classifier->classify(null, $normalizer->normalize('PMT-77'), Amount::fromDecimal('-420')));
        $this->assertSame(DocumentType::Invoice, $classifier->classify(null, $normalizer->normalize('INV-77'), Amount::fromDecimal('420')));
    }

    public function test_sign_is_the_last_resort(): void
    {
        $classifier = new DocumentTypeClassifier;

        $this->assertSame(DocumentType::Invoice, $classifier->classify(null, null, Amount::fromDecimal('10')));
        $this->assertSame(DocumentType::Unknown, $classifier->classify(null, null, Amount::fromDecimal('-10')));
    }

    public function test_balance_lines_are_recognised_by_explicit_wording_only(): void
    {
        $detector = new BalanceLineDetector;

        $this->assertTrue($detector->isBalanceLine(['Balance b/f']));
        $this->assertTrue($detector->isBalanceLine([null, 'Opening balance']));
        $this->assertTrue($detector->isBalanceLine(['Total due']));
        $this->assertTrue($detector->isBalanceLine(['Solde reporté']));
        $this->assertFalse($detector->isBalanceLine(['INV-123', 'Invoice']));
        $this->assertFalse($detector->isBalanceLine(['Balance adjustment INV-12']));
        $this->assertFalse($detector->isBalanceLine([null, '']));
        $this->assertTrue($detector->isBalanceLine(['Sous-total page']));
        $this->assertTrue($detector->isBalanceLine(['Sub-total']));
        $this->assertTrue($detector->isBalanceLine(['À reporter']));
        $this->assertTrue($detector->isBalanceLine(['Amount Due']));
        $this->assertFalse($detector->isBalanceLine(['Total for ACME Ltd']));
    }

    public function test_group_totals_are_recognised_separately(): void
    {
        $detector = new BalanceLineDetector;

        $this->assertTrue($detector->isGroupTotal(['Total for Pacific Paper Co.']));
        $this->assertTrue($detector->isGroupTotal([null, 'Total pour BRICOMAT']));
        $this->assertFalse($detector->isGroupTotal(['Total']));
        $this->assertFalse($detector->isGroupTotal(['Totally new product']));
    }
}
