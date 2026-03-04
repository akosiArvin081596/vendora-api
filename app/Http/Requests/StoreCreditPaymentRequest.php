<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreditPaymentRequest extends FormRequest
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
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where('user_id', $this->user()->id),
            ],
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', 'string', Rule::in(['cash', 'card', 'online'])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer is required.',
            'customer_id.exists' => 'Customer must be valid.',
            'amount.required' => 'Payment amount is required.',
            'amount.min' => 'Payment amount must be at least 1.',
            'paid_at.required' => 'Payment date is required.',
            'method.required' => 'Payment method is required.',
            'method.in' => 'Payment method must be cash, card, or online.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $customer = Customer::query()
                ->where('user_id', $this->user()->id)
                ->find($this->integer('customer_id'));

            if ($customer && $this->integer('amount') > $customer->credit_balance) {
                $validator->errors()->add('amount', 'Payment amount cannot exceed the customer\'s outstanding credit balance.');
            }
        });
    }
}
