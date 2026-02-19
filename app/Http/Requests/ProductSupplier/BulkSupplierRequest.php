<?php

namespace App\Http\Requests\ProductSupplier;

use Illuminate\Foundation\Http\FormRequest;

class BulkSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_ids' => ['required', 'array', 'min:1'],
            'supplier_ids.*' => ['required', 'integer', 'exists:suppliers,id'],
        ];
    }
}
