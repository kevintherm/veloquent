<?php

namespace Veloquent\Core\Domain\Hooks\Facades;

use Illuminate\Support\Facades\Facade;
use Veloquent\Core\Domain\Hooks\HookRegistry;

/**
 * @method static void register(string $event, string|array|callable $pipes) Register one or more pipes for a specific event.
 * @method static void before(string $alias, string|array|callable $pipes) Register one or more "before" pipes using a human-readable alias.
 * @method static void after(string $alias, string|array|callable $pipes) Register one or more "after" pipes using a human-readable alias.
 * @method static array pipesFor(string $event) Get all pipes registered for a specific internal event.
 *
 * @see \Veloquent\Core\Domain\Hooks\HookRegistry
 */
class Hooks extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return HookRegistry::class;
    }
}
