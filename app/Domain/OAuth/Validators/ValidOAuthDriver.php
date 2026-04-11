<?php

namespace App\Domain\OAuth\Validators;

class ValidOAuthDriver
{
    public const VALID_DRIVERS = [
        'google', 'facebook', 'x', 'github',
    ];
}
