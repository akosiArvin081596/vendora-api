<?php

namespace App\Enums;

enum UserType: string
{
    case Admin = 'admin';
    case Vendor = 'vendor';
    case Manager = 'manager';
    case Cashier = 'cashier';
    case Buyer = 'buyer';

    /**
     * Get the display label for the user type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Vendor => 'Vendor',
            self::Manager => 'Manager',
            self::Cashier => 'Cashier',
            self::Buyer => 'Buyer',
        };
    }

    /**
     * Get all user types as options.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Admin->value => self::Admin->label(),
            self::Vendor->value => self::Vendor->label(),
            self::Manager->value => self::Manager->label(),
            self::Cashier->value => self::Cashier->label(),
            self::Buyer->value => self::Buyer->label(),
        ];
    }

    /**
     * Get the hierarchy level for permission checks.
     * Higher number = more permissions.
     */
    public function hierarchyLevel(): int
    {
        return match ($this) {
            self::Admin => 4,
            self::Manager => 3,
            self::Vendor => 2,
            self::Cashier => 2,
            self::Buyer => 0,
        };
    }

    /**
     * Check if this user type can manage another user type.
     */
    public function canManage(UserType $other): bool
    {
        // Admin can manage everyone except other admins
        if ($this === self::Admin) {
            return true;
        }

        // Manager can manage cashiers and buyers
        if ($this === self::Manager) {
            return in_array($other, [self::Cashier, self::Buyer]);
        }

        return false;
    }
}
