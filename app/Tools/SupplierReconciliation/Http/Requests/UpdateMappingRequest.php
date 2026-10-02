<?php

namespace App\Tools\SupplierReconciliation\Http\Requests;

use App\Tools\SupplierReconciliation\Mapping\AmountMode;
use App\Tools\SupplierReconciliation\Mapping\ColumnMapping;
use App\Tools\SupplierReconciliation\Mapping\Field;
use App\Tools\SupplierReconciliation\Normalization\CurrencyDetector;
use App\Tools\SupplierReconciliation\Normalization\DateOrder;
use App\Tools\SupplierReconciliation\Normalization\DecimalSeparator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMappingRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'currency' => ['required', 'string', Rule::in(CurrencyDetector::codes())],
        ];

        foreach (['statement', 'ledger'] as $side) {
            $rules[$side] = ['required', 'array'];
            $rules["{$side}.columns"] = ['present', 'array'];

            foreach (Field::cases() as $field) {
                $rules["{$side}.columns.{$field->value}"] = ['nullable', 'integer', 'min:0', 'max:200'];
            }

            $rules["{$side}.amount_mode"] = ['required', Rule::enum(AmountMode::class)];
            $rules["{$side}.invoice_sign"] = ['required', Rule::in([ColumnMapping::INVOICES_POSITIVE, ColumnMapping::INVOICES_NEGATIVE])];
            $rules["{$side}.invoice_column"] = ['required', Rule::in([ColumnMapping::INVOICES_IN_DEBIT, ColumnMapping::INVOICES_IN_CREDIT])];
            $rules["{$side}.date_order"] = ['required', Rule::enum(DateOrder::class)];
            $rules["{$side}.decimal_separator"] = ['required', Rule::enum(DecimalSeparator::class)];
            $rules["{$side}.sign_from_type"] = ['boolean'];
            $rules["{$side}.supplier_filter"] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }
}
