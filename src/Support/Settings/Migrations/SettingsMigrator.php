<?php

namespace Veloquent\Core\Support\Settings\Migrations;

use Illuminate\Support\Facades\DB;

class SettingsMigrator
{
    /**
     * Add a setting.
     *
     * @param string $property The fully-qualified property name (e.g. 'general.app_name')
     * @param mixed $value
     * @param bool $locked
     * @return void
     */
    public function add(string $property, mixed $value, bool $locked = false): void
    {
        if (str_contains($property, '.')) {
            [$group, $name] = explode('.', $property, 2);
        } else {
            $group = 'default';
            $name = $property;
        }

        DB::table('settings')->updateOrInsert(
            ['group' => $group, 'name' => $name],
            [
                'payload' => json_encode($value),
                'locked' => $locked,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Delete a setting if it exists.
     *
     * @param string $property The fully-qualified property name (e.g. 'general.timezone')
     * @return void
     */
    public function deleteIfExists(string $property): void
    {
        if (str_contains($property, '.')) {
            [$group, $name] = explode('.', $property, 2);
        } else {
            $group = 'default';
            $name = $property;
        }

        DB::table('settings')
            ->where('group', $group)
            ->where('name', $name)
            ->delete();
    }
}
