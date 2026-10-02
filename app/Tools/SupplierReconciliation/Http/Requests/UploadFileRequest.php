<?php

namespace App\Tools\SupplierReconciliation\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadFileRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.(int) config('supplier-reconciliation.limits.max_file_kilobytes'),
                'extensions:csv,txt,tsv,xlsx',
            ],
            // Worksheet to read in a workbook (Excel limits names to 31 characters).
            'sheet' => ['nullable', 'string', 'max:31'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose a CSV or XLSX file.',
            'file.uploaded' => 'The file could not be uploaded. It may be too large.',
            'file.max' => 'This file is too large (maximum '.round((int) config('supplier-reconciliation.limits.max_file_kilobytes') / 1024).' MB).',
            'file.extensions' => 'Only CSV and XLSX files are accepted. Save legacy XLS or other formats as XLSX or CSV first.',
        ];
    }
}
