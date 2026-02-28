<?php

namespace App\Http\Requests;

use App\Enums\ReservationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFoodMenuReservationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->isVendor() || $user?->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['sometimes', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'servings' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', Rule::in(array_column(ReservationStatus::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reserved_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'servings.min' => 'At least 1 serving is required.',
            'status.in' => 'Invalid reservation status.',
        ];
    }
}
