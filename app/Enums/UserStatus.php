<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Suspended => 'Suspended',
        };
    }

    /**
     * Get all statuses as options.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Active->value => self::Active->label(),
            self::Inactive->value => self::Inactive->label(),
            self::Suspended->value => self::Suspended->label(),
        ];
    }
}
