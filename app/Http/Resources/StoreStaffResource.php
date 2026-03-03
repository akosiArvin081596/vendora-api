<?php

namespace App\Http\Resources;

use App\Enums\StoreRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreStaffResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = StoreRole::tryFrom($this->pivot?->role ?? '');
        $roleDefaults = $role?->permissions() ?? [];
        $customPermissions = $this->decodePivotPermissions();
        $hasCustom = is_array($customPermissions) && count($customPermissions) > 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->pivot?->role,
            'role_label' => $role?->label(),
            'role_default_permissions' => $roleDefaults,
            'custom_permissions' => $hasCustom ? $customPermissions : null,
            'effective_permissions' => $hasCustom ? $customPermissions : $roleDefaults,
            'assigned_at' => $this->pivot?->assigned_at,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Decode the permissions from the pivot, handling both array and JSON string.
     *
     * @return array<string>|null
     */
    private function decodePivotPermissions(): ?array
    {
        $raw = $this->pivot?->permissions;

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
