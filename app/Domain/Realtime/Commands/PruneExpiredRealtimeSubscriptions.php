<?php

namespace App\Domain\Realtime\Commands;

use App\Models\RealtimeSubscription;
use Illuminate\Console\Command;

class PruneExpiredRealtimeSubscriptions extends Command
{
    protected $signature = 'realtime:prune-expired';

    protected $description = 'Prune expired realtime subscriptions from the database';

    public function handle(): int
    {
        $deletedCount = RealtimeSubscription::query()
            ->where('expired_at', '<=', now())
            ->delete();

        $this->info("[realtime:prune-expired] Deleted {$deletedCount} expired subscription(s).");

        return self::SUCCESS;
    }
}
