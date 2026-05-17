<?php

namespace Veloquent\Core\Support\Settings\Casts;

interface SettingsCast
{
    /**
     * Get the property value from the payload.
     *
     * @param mixed $payload
     * @return mixed
     */
    public function get($payload);

    /**
     * Set the payload value from the property.
     *
     * @param mixed $payload
     * @return mixed
     */
    public function set($payload);
}
