<?php

namespace App\Tools\SupplierReconciliation\Import;

enum FileFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';

    public function label(): string
    {
        return match ($this) {
            self::Csv => 'CSV',
            self::Xlsx => 'Excel (XLSX)',
        };
    }
}
