<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'internal_code' => ['required', 'string', 'max:255', 'unique:products,internal_code'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
