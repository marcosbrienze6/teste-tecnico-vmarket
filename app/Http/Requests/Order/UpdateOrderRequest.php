<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['sometimes', 'integer', 'exists:suppliers,id'],
            'order_date' => ['sometimes', 'date'],
            'status' => ['sometimes', Rule::in(['open', 'processing', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
