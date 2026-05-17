<?php

namespace Veloquent\Core\Support\Settings\Migrations;

use Illuminate\Database\Migrations\Migration;

abstract class SettingsMigration extends Migration
{
    /**
     * The settings migrator instance.
     *
     * @var SettingsMigrator
     */
    protected SettingsMigrator $migrator;

    /**
     * Create a new settings migration instance.
     */
    public function __construct()
    {
        $this->migrator = new SettingsMigrator();
    }
}
