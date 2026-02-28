<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
        };
    }

    public static function options(): array
    {
        return [
            self::Pending->value => self::Pending->label(),
            self::Confirmed->value => self::Confirmed->label(),
            self::Cancelled->value => self::Cancelled->label(),
            self::Completed->value => self::Completed->label(),
        ];
    }
}
