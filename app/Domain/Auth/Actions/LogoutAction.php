<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Services\TokenAuthService;
use App\Domain\Realtime\Contracts\RealtimeBusDriver;
use App\Domain\Records\Models\Record;
use Illuminate\Http\Request;
use Spatie\Multitenancy\Contracts\IsTenant;

class LogoutAction
{
    public function __construct(private TokenAuthService $tokenService, private RealtimeBusDriver $realtimeBus) {}

    /**
     * Revoke the current token for the given user and publish a realtime
     * logout message.
     */
    public function execute(Request $request, Record $user): void
    {
        $tenantId = $this->resolveTenantId();
        $tokenHash = hash('sha256', $this->tokenService->extractTokenFromRequest($request));
        $this->tokenService->revokeRecordTokens($user->collection->id, $user->id, $tokenHash);

        if ($tenantId) $this->realtimeBus->publish([
            'type' => 'connection',
            'action' => 'logout',
            'tenant_id' => $tenantId,
            'auth_collection' => $user->getTable(),
            'subscriber_id' => (string) $user->getKey(),
        ]);
    }

    private function resolveTenantId(): string|null
    {
        return data_get(app(IsTenant::class)::current(), 'id');
    }
}
