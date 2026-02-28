<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicFoodMenuReservationRequest extends FormRequest
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
            'food_menu_item_id' => ['required', 'integer', 'exists:food_menu_items,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'servings' => ['required', 'integer', 'min:1'],
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
            'food_menu_item_id.required' => 'Menu item is required.',
            'food_menu_item_id.exists' => 'Menu item must be valid.',
            'customer_name.required' => 'Customer name is required.',
            'customer_phone.required' => 'Customer phone is required.',
            'servings.required' => 'Number of servings is required.',
            'servings.min' => 'At least 1 serving is required.',
        ];
    }
}
