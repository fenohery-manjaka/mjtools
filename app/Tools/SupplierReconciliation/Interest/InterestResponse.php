<?php

namespace App\Tools\SupplierReconciliation\Interest;

use Illuminate\Database\Eloquent\Model;

/**
 * One answer to the "save this supplier" question asked after a successful
 * free reconciliation: the signal used to decide whether to build the paid
 * product (spec §43, §50). It holds no accounting data and no link to a run;
 * the email is optional and only used to announce the paid feature.
 *
 * @property int $id
 * @property string $suppliers_per_month
 * @property string $accounting_software
 * @property string|null $accounting_software_other
 * @property string $price_answer
 * @property list<string>|null $wanted_next
 * @property string $price_shown
 * @property string|null $email
 */
class InterestResponse extends Model
{
    public const SUPPLIERS_PER_MONTH = [
        '1-5' => '1 to 5',
        '6-20' => '6 to 20',
        '21-50' => '21 to 50',
        '51-100' => '51 to 100',
        '100+' => 'More than 100',
    ];

    public const ACCOUNTING_SOFTWARE = [
        'xero' => 'Xero',
        'quickbooks' => 'QuickBooks',
        'sage' => 'Sage',
        'netsuite' => 'NetSuite',
        'business_central' => 'Microsoft Dynamics / Business Central',
        'spreadsheets' => 'Spreadsheets only',
        'other' => 'Other',
    ];

    public const PRICE_ANSWERS = [
        'yes' => 'Yes, at this price',
        'maybe' => 'Maybe',
        'lower' => 'Only at a lower price',
        'no' => 'No',
    ];

    /** Later capabilities (scope C), asked separately from the paid product (spec §49). */
    public const WANTED_NEXT = [
        'batch' => 'Reconcile several suppliers at once',
        'pdf' => 'Read PDF statements',
        'integration' => 'Connect my accounting software',
        'email' => 'Forward statements by email',
        'exceptions' => 'Follow open exceptions from one period to the next',
    ];

    protected $table = 'supplier_reconciliation_interest';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['wanted_next' => 'array'];
    }
}
