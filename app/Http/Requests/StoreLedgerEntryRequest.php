<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLedgerEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['stock_in', 'expense'])],
            'product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where('user_id', $this->user()->id),
            ],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'amount' => ['nullable', 'integer'],
            'description' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Entry type is required.',
            'type.in' => 'Entry type must be stock_in or expense.',
            'product_id.exists' => 'Product must be valid.',
            'quantity.min' => 'Quantity must be at least 1.',
            'description.required' => 'Description is required.',
        ];
    }
}
