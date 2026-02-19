<?php

namespace App\Http\Requests\ProductSupplier;

use Illuminate\Foundation\Http\FormRequest;

class LinkSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
        ];
    }
}
