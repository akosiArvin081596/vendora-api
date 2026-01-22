<?php

namespace App\Http\Requests;

use App\Enums\StoreRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
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
        $assignableRoles = array_map(
            fn (StoreRole $role) => $role->value,
            StoreRole::assignable()
        );

        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', 'string', Rule::in($assignableRoles)],
            'permissions' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Staff email is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.exists' => 'No user found with this email.',
            'role.required' => 'Role is required.',
            'role.in' => 'Role must be one of: manager, cashier, staff.',
        ];
    }
}
