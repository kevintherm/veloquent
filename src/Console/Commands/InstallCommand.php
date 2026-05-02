<?php
/*
 * (c) Kevin <kevindrm0@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Veloquent\Core\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:install {--force : Overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install and initialize Veloquent Core';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->components->info('Installing Veloquent Core...');

        $this->components->task('Publishing configuration...', function () {
            $this->call('vendor:publish', [
                '--tag' => 'velo-config',
                '--force' => $this->option('force'),
            ]);
        });

        $this->components->task('Publishing migrations...', function () {
            $this->call('vendor:publish', [
                '--tag' => 'velo-migrations',
                '--force' => $this->option('force'),
            ]);
        });

        $this->components->task('Publishing assets...', function () {
            $this->call('vendor:publish', [
                '--tag' => 'velo-assets',
                '--force' => $this->option('force'),
            ]);
        });

        $this->components->task('Running landlord migrations...', function () {
            $this->call('migrate', [
                '--path' => 'database/migrations/landlord',
            ]);
        });

        if ($this->confirm('Would you like to create your first tenant?', true)) {
            $name = $this->ask('Tenant Name', 'Acme');
            $domain = $this->ask('Tenant Domain', 'localhost');

            $this->call('tenants:create', [
                'name' => $name,
                '--domain' => $domain,
            ]);
        }

        $this->components->info('Veloquent Core installed successfully!');
    }
}
