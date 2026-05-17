<?php

namespace Veloquent\Core\Support\Settings;

class SettingsContainer
{
    /**
     * Registered settings classes.
     *
     * @var array<int, string>
     */
    protected array $classes = [];

    /**
     * Register a setting class.
     *
     * @param string $class
     * @return void
     */
    public function register(string $class): void
    {
        $this->classes[] = $class;
    }

    /**
     * Get all registered settings classes.
     *
     * @return array<int, string>
     */
    public function getSettingClasses(): array
    {
        return $this->classes;
    }
}
