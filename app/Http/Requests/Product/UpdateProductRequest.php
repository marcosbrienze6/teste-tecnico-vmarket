<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'internal_code' => ['required', 'string', 'max:255', Rule::unique('products', 'internal_code')->ignore($product->id)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
