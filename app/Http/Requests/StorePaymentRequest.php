<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
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
            'order_id' => [
                'required',
                'integer',
                Rule::exists('orders', 'id')->where('user_id', $this->user()->id),
            ],
            'paid_at' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', Rule::in(['cash', 'card', 'online'])],
            'status' => ['required', 'string', Rule::in(['completed', 'pending', 'refunded'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_id.required' => 'Order is required.',
            'order_id.exists' => 'Order must be valid.',
            'paid_at.required' => 'Payment date is required.',
            'amount.required' => 'Payment amount is required.',
            'amount.min' => 'Payment amount must be at least 1.',
            'method.required' => 'Payment method is required.',
            'method.in' => 'Payment method must be cash, card, or online.',
            'status.required' => 'Payment status is required.',
            'status.in' => 'Payment status must be completed, pending, or refunded.',
        ];
    }
}
