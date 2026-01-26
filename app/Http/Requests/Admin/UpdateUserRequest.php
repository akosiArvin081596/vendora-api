<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('user')->id ?? $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'user_type' => ['sometimes', 'string', 'in:admin,vendor,manager,cashier,buyer'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'string', 'in:active,inactive,suspended'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.email' => 'Please enter a valid email address',
            'email.unique' => 'This email is already registered',
            'password.min' => 'Password must be at least 8 characters',
            'user_type.in' => 'Invalid user type selected',
            'status.in' => 'Invalid status selected',
        ];
    }
}
