<?php

namespace App\Tools\SupplierReconciliation\Normalization;

use App\Tools\SupplierReconciliation\Domain\Amount;
use App\Tools\SupplierReconciliation\Domain\DocumentType;
use App\Tools\SupplierReconciliation\Domain\NormalizedReference;

/**
 * Infers the nature of a transaction from an explicit type column first,
 * then from well-known reference prefixes, then from the sign.
 */
final class DocumentTypeClassifier
{
    private const CREDIT_WORDS = '/\b(credit ?notes?|credit memo|cr ?note|crn|cn|avoirs?|note de credit|gutschrift|abono|refund|rebate)\b/i';

    private const PAYMENT_WORDS = '/\b(payments?|paiements?|reglements?|virements?|pmt|pymt|pay|receipt|remittance|bacs|chq|cheque|check|transfer|direct debit|dd|wire)\b/i';

    private const INVOICE_WORDS = '/\b(invoices?|factures?|inv|bill|rechnung|factura|debit note)\b/i';

    private const CREDIT_PREFIXES = ['CN', 'CRN', 'CR', 'AV', 'NC', 'CRE', 'CM'];

    private const PAYMENT_PREFIXES = ['PMT', 'PAY', 'PYMT', 'REC', 'RCPT', 'CHQ'];

    private const INVOICE_PREFIXES = ['INV', 'IN', 'FA', 'FAC', 'SI', 'BILL'];

    /**
     * Classifies from the type column only.
     */
    public function fromTypeText(?string $type): ?DocumentType
    {
        $text = $this->simplify($type);

        if ($text === '') {
            return null;
        }

        return match (true) {
            preg_match(self::CREDIT_WORDS, $text) === 1 => DocumentType::Credit,
            preg_match(self::PAYMENT_WORDS, $text) === 1 => DocumentType::Payment,
            preg_match(self::INVOICE_WORDS, $text) === 1 => DocumentType::Invoice,
            default => null,
        };
    }

    public function classify(?string $type, ?NormalizedReference $reference, ?Amount $amount): DocumentType
    {
        $fromType = $this->fromTypeText($type);

        if ($fromType !== null) {
            return $fromType;
        }

        $prefix = $reference === null ? '' : explode('|', $reference->letters)[0];

        if ($prefix !== '' && $reference !== null && $reference->digitCore !== '') {
            if (in_array($prefix, self::CREDIT_PREFIXES, true)) {
                return DocumentType::Credit;
            }

            if (in_array($prefix, self::PAYMENT_PREFIXES, true)) {
                return DocumentType::Payment;
            }

            if (in_array($prefix, self::INVOICE_PREFIXES, true) && ($amount === null || ! $amount->isNegative())) {
                return DocumentType::Invoice;
            }
        }

        if ($amount !== null && $amount->isPositive()) {
            return DocumentType::Invoice;
        }

        return DocumentType::Unknown;
    }

    private function simplify(?string $text): string
    {
        $text = mb_strtolower(trim((string) $text));

        return strtr($text, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ç' => 'c', 'û' => 'u']);
    }
}
