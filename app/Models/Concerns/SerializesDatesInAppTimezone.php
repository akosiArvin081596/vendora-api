<?php

namespace App\Models\Concerns;

use DateTimeInterface;
use DateTimeZone;

trait SerializesDatesInAppTimezone
{
    /**
     * Prepare a date for array / JSON serialization.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        $timezone = new DateTimeZone(config('app.timezone', 'Asia/Manila'));

        return $date->setTimezone($timezone)->format('Y-m-d\TH:i:sP');
    }
}
