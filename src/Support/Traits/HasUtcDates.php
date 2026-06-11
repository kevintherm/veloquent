<?php

namespace Veloquent\Core\Support\Traits;

use Carbon\Carbon;
use DateTimeInterface;

trait HasUtcDates
{
    private ?string $currentSerializingAttribute = null;

    protected function addDateAttributesToArray(array $attributes)
    {
        $this->currentSerializingAttribute = null;
        return parent::addDateAttributesToArray($attributes);
    }

    protected function addCastAttributesToArray(array $attributes, array $mutatedAttributes)
    {
        $this->currentSerializingAttribute = null;
        try {
            return parent::addCastAttributesToArray($attributes, $mutatedAttributes);
        } finally {
            $this->currentSerializingAttribute = null;
        }
    }

    protected function castAttribute($key, $value)
    {
        $this->currentSerializingAttribute = $key;
        return parent::castAttribute($key, $value);
    }

    protected function mutateAttributeForArray($key, $value)
    {
        $this->currentSerializingAttribute = $key;
        try {
            return parent::mutateAttributeForArray($key, $value);
        } finally {
            $this->currentSerializingAttribute = null;
        }
    }

    /**
     * Prepare a date for array / JSON serialization.
     * Enforces UTC timezone and standard ISO-8601 format.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        if ($this->currentSerializingAttribute !== null) {
            $casts = $this->getCasts();
            $cast = $casts[$this->currentSerializingAttribute] ?? null;
            if ($cast === 'date' || $cast === 'immutable_date') {
                return $date->format('Y-m-d');
            }
        }

        return Carbon::instance($date)->setTimezone('UTC')->format('Y-m-d\TH:i:s.u\Z');
    }
}
