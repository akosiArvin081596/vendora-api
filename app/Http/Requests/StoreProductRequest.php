<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sku' => [
                'required',
                'string',
                'max:50',
                Rule::unique('products', 'sku')->where('user_id', $this->user()->id),
            ],
            'barcode' => ['nullable', 'string', 'max:100', 'unique:products,barcode'],
            'category_id' => ['required_without:category', 'integer', 'exists:categories,id'],
            'category' => ['required_without:category_id', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'unit' => ['required', 'string', 'max:20'],
            'stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'max_stock' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'is_active' => ['required', 'boolean'],
            'is_ecommerce' => ['required', 'boolean'],
            'bulk_pricing' => ['nullable', 'array'],
            'bulk_pricing.*.min_qty' => ['required', 'integer', 'min:2'],
            'bulk_pricing.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required.',
            'sku.required' => 'SKU is required.',
            'sku.unique' => 'SKU must be unique.',
            'barcode.unique' => 'Barcode must be unique.',
            'category_id.required' => 'Category is required.',
            'category_id.exists' => 'Category must be valid.',
            'category.required' => 'Category is required.',
            'price.required' => 'Price is required.',
            'price.numeric' => 'Price must be a number.',
            'cost.numeric' => 'Cost must be a number.',
            'currency.required' => 'Currency is required.',
            'currency.size' => 'Currency must be a 3-letter code.',
            'unit.required' => 'Unit is required.',
            'stock.required' => 'Stock is required.',
            'stock.integer' => 'Stock must be an integer.',
            'min_stock.integer' => 'Minimum stock must be an integer.',
            'max_stock.integer' => 'Maximum stock must be an integer.',
            'is_active.required' => 'Active status is required.',
            'is_ecommerce.required' => 'E-commerce status is required.',
            'bulk_pricing.array' => 'Bulk pricing must be an array.',
            'bulk_pricing.*.min_qty.required' => 'Minimum quantity is required for each bulk price tier.',
            'bulk_pricing.*.min_qty.integer' => 'Minimum quantity must be an integer.',
            'bulk_pricing.*.min_qty.min' => 'Minimum quantity must be at least 2.',
            'bulk_pricing.*.price.required' => 'Price is required for each bulk price tier.',
            'bulk_pricing.*.price.numeric' => 'Price must be a number.',
            'bulk_pricing.*.price.min' => 'Price must be at least 0.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('category') && ! $this->filled('category_id')) {
            $categoryValue = $this->string('category')->value();
            $category = Category::query()
                ->where('slug', $categoryValue)
                ->orWhere('name', $categoryValue)
                ->first();

            if ($category) {
                $this->merge(['category_id' => $category->id]);
            }
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('category') && ! $this->filled('category_id')) {
                $validator->errors()->add('category', 'Category must be valid.');
            }
        });
    }
}
