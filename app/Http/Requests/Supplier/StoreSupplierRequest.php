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
            'cnpj' => ['required', 'string', 'max:18', Rule::unique('suppliers', 'cnpj')->whereNull('deleted_at')],
            'email' => ['required', 'email', 'max:255', Rule::unique('suppliers', 'email')->whereNull('deleted_at')],
            'phone' => ['required', 'string', 'max:20'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
