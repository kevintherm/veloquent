<?php

namespace Veloquent\Core\Domain\Settings;

use Veloquent\Core\Support\Settings\Settings;

class RateLimitSettings extends Settings
{
    public bool $rate_limit_enabled = true;

    public array $rate_limit_rules = [
        [
            'label' => '*:otp',
            'max_attempts' => 5,
            'decay_minutes' => 1,
            'audience' => 'all',
        ],
        [
            'label' => '*:auth',
            'max_attempts' => 10,
            'decay_minutes' => 1,
            'audience' => 'guest',
        ],
        [
            'label' => '*',
            'max_attempts' => 240,
            'decay_minutes' => 1,
            'audience' => 'all',
        ],
    ];

    public static function group(): string
    {
        return 'rate_limit';
    }
}
