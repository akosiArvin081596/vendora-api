<?php

namespace App\Http\Requests;

use App\Enums\StoreRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateStoreStaffRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'string', Rule::in($assignableRoles)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(StoreRole::allPermissions())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Staff name is required.',
            'email.required' => 'Staff email is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.unique' => 'A user with this email already exists.',
            'password.required' => 'Password is required.',
            'role.required' => 'Role is required.',
            'role.in' => 'Role must be one of: manager, cashier, staff.',
            'permissions.*.in' => 'Invalid permission: :input.',
        ];
    }
}
