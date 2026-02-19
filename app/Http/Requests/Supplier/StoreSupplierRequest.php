<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'cnpj' => ['required', 'string', 'max:18', 'unique:suppliers,cnpj'],
            'email' => ['required', 'email', 'max:255', 'unique:suppliers,email'],
            'phone' => ['required', 'string', 'max:20'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
