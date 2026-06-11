<?php
/*
 * (c) Kevin <kevindrm0@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Veloquent\Core\Console\Commands;

use Illuminate\Console\Command;
use Veloquent\Core\Domain\Auth\Models\AuthToken;

class PruneExpiredAuthTokensCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:prune-tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune expired or revoked auth tokens from the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deletedCount = AuthToken::query()
            ->where(function ($query) {
                $query->where('expires_at', '<=', now()->toDateTimeString())
                    ->orWhereNotNull('revoked_at');
            })
            ->delete();

        $this->info("[auth:prune-tokens] Deleted {$deletedCount} expired or revoked auth token(s).");

        return self::SUCCESS;
    }
}
