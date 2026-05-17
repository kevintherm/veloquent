<?php

namespace Veloquent\Core\Support\Traits;

use Carbon\Carbon;
use DateTimeInterface;

trait HasUtcDates
{
    /**
     * Prepare a date for array / JSON serialization.
     * Enforces UTC timezone and standard ISO-8601 format.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)->setTimezone('UTC')->format('Y-m-d\TH:i:s.u\Z');
    }
}
