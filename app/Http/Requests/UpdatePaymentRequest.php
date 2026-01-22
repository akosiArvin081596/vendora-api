<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
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
            'paid_at' => ['sometimes', 'date'],
            'amount' => ['sometimes', 'integer', 'min:1'],
            'method' => ['sometimes', 'string', Rule::in(['cash', 'card', 'online'])],
            'status' => ['sometimes', 'string', Rule::in(['completed', 'pending', 'refunded'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.min' => 'Payment amount must be at least 1.',
            'method.in' => 'Payment method must be cash, card, or online.',
            'status.in' => 'Payment status must be completed, pending, or refunded.',
        ];
    }
}
