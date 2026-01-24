<?php

namespace App\Enums;

enum UserType: string
{
    case Admin = 'admin';
    case Vendor = 'vendor';
    case Buyer = 'buyer';

    /**
     * Get the display label for the user type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Vendor => 'Vendor',
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
            self::Buyer->value => self::Buyer->label(),
        ];
    }
}
