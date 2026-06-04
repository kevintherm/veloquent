<?php

namespace Veloquent\Core\Domain\Auth\Actions;

use Illuminate\Http\Request;
use Spatie\Multitenancy\Contracts\IsTenant;
use Veloquent\Core\Domain\Hooks\Contracts\HookRunner;
use Veloquent\Core\Domain\Hooks\ValueObjects\HookPayload;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Auth\Contracts\TokenAuthService;
use Veloquent\Core\Domain\Realtime\Contracts\RealtimeBusDriver;

class LogoutAction
{
    public function __construct(
        private TokenAuthService $tokenService,
        private RealtimeBusDriver $realtimeBus,
        private HookRunner $hookRunner,
    ) {}

    /**
     * Revoke the current token for the given user and publish a realtime
     * logout message.
     */
    public function execute(Request $request, Record $user): void
    {
        $this->hookRunner->run(new HookPayload(
            event: 'auth.logging_out',
            collection: $user->collection,
            record: $user,
            request: $request,
        ));

        $tenantId = $this->resolveTenantId();
        $tokenHash = hash('sha256', $this->tokenService->extractTokenFromRequest($request));
        $this->tokenService->revokeRecordTokens($user->collection->id, $user->id, $tokenHash);

        $this->hookRunner->run(new HookPayload(
            event: 'auth.logged_out',
            collection: $user->collection,
            record: $user,
            request: $request,
        ));

        if ($tenantId) {
            $this->realtimeBus->publish([
                'type' => 'connection',
                'action' => 'logout',
                'tenant_id' => $tenantId,
                'auth_collection' => $user->getTable(),
                'subscriber_id' => (string) $user->getKey(),
            ]);
        }
    }

    private function resolveTenantId(): ?string
    {
        return data_get(app(IsTenant::class)::current(), 'id');
    }
}
