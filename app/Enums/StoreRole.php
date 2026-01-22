<?php

namespace App\Enums;

enum StoreRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Cashier = 'cashier';
    case Staff = 'staff';

    /**
     * Get the permissions for this role.
     *
     * @return array<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => ['*'],
            self::Manager => [
                'products.view',
                'products.create',
                'products.update',
                'orders.view',
                'orders.create',
                'orders.update',
                'inventory.view',
                'inventory.adjust',
                'customers.view',
                'customers.create',
                'customers.update',
                'payments.view',
                'payments.create',
                'reports.view',
                'staff.view',
            ],
            self::Cashier => [
                'products.view',
                'orders.view',
                'orders.create',
                'customers.view',
                'customers.create',
                'payments.view',
                'payments.create',
            ],
            self::Staff => [
                'products.view',
                'orders.view',
                'inventory.view',
            ],
        };
    }

    /**
     * Check if the role has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions();

        if (in_array('*', $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Cashier => 'Cashier',
            self::Staff => 'Staff',
        };
    }

    /**
     * Get all roles as options.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Owner->value => self::Owner->label(),
            self::Manager->value => self::Manager->label(),
            self::Cashier->value => self::Cashier->label(),
            self::Staff->value => self::Staff->label(),
        ];
    }

    /**
     * Get assignable roles (roles that can be assigned to staff).
     *
     * @return array<self>
     */
    public static function assignable(): array
    {
        return [
            self::Manager,
            self::Cashier,
            self::Staff,
        ];
    }
}
