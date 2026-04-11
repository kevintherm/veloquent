<?php

namespace App\Infrastructure\Models;

use App\Observers\TenantObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Spatie\Multitenancy\Models\Tenant as SpatieTenant;

#[ObservedBy(TenantObserver::class)]
class Tenant extends SpatieTenant
{
    protected $fillable = [
        'name',
        'domain',
        'database',
    ];
}
