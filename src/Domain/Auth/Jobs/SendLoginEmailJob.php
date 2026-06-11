<?php

namespace Veloquent\Core\Domain\Auth\Jobs;

use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Emails\Services\EmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendLoginEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $email,
        public Collection $collection,
        public string $loginTime,
        public string $ipAddress,
    ) {}

    public function handle(EmailService $emailService): void
    {
        $emailService->send(
            $this->email,
            'login',
            $this->collection,
            [
                'login_time' => $this->loginTime,
                'ip_address' => $this->ipAddress,
            ]
        );
    }
}
