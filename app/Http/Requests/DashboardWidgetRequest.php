<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DashboardWidgetRequest extends FormRequest
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
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.date_format' => 'Start date must be in the format YYYY-MM-DD.',
            'end_date.date_format' => 'End date must be in the format YYYY-MM-DD.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
            'limit.integer' => 'Limit must be a whole number.',
            'limit.min' => 'Limit must be at least 1.',
            'limit.max' => 'Limit may not be greater than 25.',
        ];
    }
}
