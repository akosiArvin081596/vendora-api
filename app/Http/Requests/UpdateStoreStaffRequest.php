<?php

namespace App\Http\Requests;

use App\Enums\StoreRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStoreStaffRequest extends FormRequest
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
            'role.required' => 'Role is required.',
            'role.in' => 'Role must be one of: manager, cashier, staff.',
        ];
    }
}
