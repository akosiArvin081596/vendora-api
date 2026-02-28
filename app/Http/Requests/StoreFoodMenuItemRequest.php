<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFoodMenuItemRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'image_base64' => ['nullable', 'string'],
            'total_servings' => ['required', 'integer', 'min:0'],
            'is_available' => ['nullable', 'boolean'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Menu item name is required.',
            'price.required' => 'Price is required.',
            'price.numeric' => 'Price must be a number.',
            'total_servings.required' => 'Total servings is required.',
            'total_servings.integer' => 'Total servings must be an integer.',
            'currency.size' => 'Currency must be a 3-letter code.',
        ];
    }
}
