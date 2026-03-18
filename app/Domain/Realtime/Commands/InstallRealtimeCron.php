<?php

namespace App\Domain\Realtime\Commands;

use Illuminate\Console\Command;

class InstallRealtimeCron extends Command
{
    protected $signature = 'realtime:install-cron';

    protected $description = 'Output cron entry for the realtime worker (shared hosting mode)';

    public function handle(): int
    {
        $appPath = base_path();
        $cronLine = "* * * * * cd {$appPath} && php artisan realtime:worker >> /dev/null 2>&1";

        $this->info('Add this line to your crontab (via hosting panel or crontab -e):');
        $this->newLine();
        $this->line('  '.$cronLine);
        $this->newLine();
        $this->line('Required .env settings for shared hosting:');
        $this->line('  VELO_REALTIME_MODE=cron');
        $this->line('  VELO_REALTIME_TTL=55');
        $this->line('  VELO_REALTIME_BUS=filesystem   # or redis if available');
        $this->newLine();
        $this->warn('Note: In cron mode the worker restarts every ~60 seconds.');
        $this->warn('A ~5 second gap between restart cycles is expected behavior.');
        $this->warn('For gap-free realtime, use a VPS with Supervisor.');

        return self::SUCCESS;
    }
}
