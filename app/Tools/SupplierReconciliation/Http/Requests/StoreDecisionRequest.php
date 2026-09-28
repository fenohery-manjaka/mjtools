<?php

namespace App\Tools\SupplierReconciliation\Http\Requests;

use App\Tools\SupplierReconciliation\Review\DecisionAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDecisionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(DecisionAction::class)],
            'item_id' => ['nullable', 'string', 'max:100'],
            'statement_ids' => ['array', 'max:20'],
            'statement_ids.*' => ['string', 'max:20'],
            'ledger_ids' => ['array', 'max:20'],
            'ledger_ids.*' => ['string', 'max:20'],
        ];
    }
}
