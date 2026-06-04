<?php

namespace Veloquent\Core\Domain\Hooks\Contracts;

interface HookRegistry
{
    /**
     * Register one or more pipes for a specific event.
     */
    public function register(string $event, string|array|callable $pipes): void;

    /**
     * Register one or more "before" pipes using a human-readable alias.
     */
    public function before(string $alias, string|array|callable $pipes): void;

    /**
     * Register one or more "after" pipes using a human-readable alias.
     */
    public function after(string $alias, string|array|callable $pipes): void;

    /**
     * Get all pipes registered for a specific internal event.
     */
    public function pipesFor(string $event): array;

    /**
     * Check if the event is mapped to an "after" type.
     */
    public function isAfterEvent(string $event): bool;

    /**
     * Unregister pipes for a specific event.
     * If $pipe is null, all pipes for that event are removed.
     */
    public function unregister(string $event, string|callable|null $pipe = null): void;

    /**
     * Get all currently registered pipes.
     */
    public function all(): array;

    /**
     * Explicitly set/overwrite the pipes for a specific event.
     */
    public function set(string $event, array $pipes): void;
}
