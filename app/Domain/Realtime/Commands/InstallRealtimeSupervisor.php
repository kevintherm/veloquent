<?php

namespace App\Domain\Realtime\Commands;

use Illuminate\Console\Command;

class InstallRealtimeSupervisor extends Command
{
    protected $signature = 'realtime:install-supervisor';

    protected $description = 'Output Supervisor config for the realtime worker';

    public function handle(): int
    {
        $appPath = base_path();
        $user = 'www-data';

        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $processUser = posix_getpwuid(posix_geteuid());
            $user = $processUser['name'] ?? $user;
        }

        $config = <<<INI
[program:velo-realtime]
command=php {$appPath}/artisan realtime:worker
directory={$appPath}
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user={$user}
numprocs=1
redirect_stderr=true
stdout_logfile={$appPath}/storage/logs/realtime-worker.log
stopwaitsecs=10
INI;

        $stubPath = base_path('velo-realtime.conf');
        file_put_contents($stubPath, $config);

        $this->info('Supervisor config written to: '.$stubPath);
        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Copy the file:  sudo cp velo-realtime.conf /etc/supervisor/conf.d/');
        $this->line('  2. Reload:         sudo supervisorctl reread && sudo supervisorctl update');
        $this->line('  3. Start:          sudo supervisorctl start velo-realtime');
        $this->newLine();
        $this->line('Make sure VELO_REALTIME_MODE=persistent is set in your .env');

        return self::SUCCESS;
    }
}
