<?php

use Veloquent\Core\Domain\Hooks\HookPayload;
use Veloquent\Core\Domain\Hooks\HookRegistry;
use Veloquent\Core\Domain\Hooks\HookRunner;
use Veloquent\Core\Domain\Hooks\Contracts\HookPipe;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Illuminate\Support\Facades\Facade;

it('can register and retrieve pipes', function () {
    $registry = new HookRegistry();
    $registry->register('test.event', 'PipeClass');

    expect($registry->pipesFor('test.event'))->toBe(['PipeClass']);
});

it('supports event aliasing with before', function () {
    $registry = new HookRegistry();
    $registry->before('record.create', 'BeforePipe');

    expect($registry->pipesFor('record.creating'))->toBe(['BeforePipe']);
});

it('supports event aliasing with after', function () {
    $registry = new HookRegistry();
    $registry->after('record.create', 'AfterPipe');

    expect($registry->pipesFor('record.created'))->toBe(['AfterPipe']);
});

it('can run a pipeline and mutate data', function () {
    $registry = new HookRegistry();
    $registry->register('test.event', [
        new class implements HookPipe {
            public function handle(HookPayload $payload, Closure $next): mixed {
                $payload->data['foo'] = 'bar';
                return $next($payload);
            }
        }
    ]);

    $runner = new HookRunner($registry);
    $collection = new Collection();
    $payload = new HookPayload('test.event', $collection, data: ['foo' => 'orig']);

    $result = $runner->run($payload);

    expect($result->data['foo'])->toBe('bar');
});

it('can halt a pipeline via exception', function () {
    $registry = new HookRegistry();
    $registry->register('test.event', [
        new class implements HookPipe {
            public function handle(HookPayload $payload, Closure $next): mixed {
                throw new \RuntimeException('Halted');
            }
        }
    ]);

    $runner = new HookRunner($registry);
    $collection = new Collection();
    $payload = new HookPayload('test.event', $collection);

    expect(fn() => $runner->run($payload))->toThrow(\RuntimeException::class, 'Halted');
});

it('silences and logs exceptions in after hooks', function () {
    $registry = new HookRegistry();
    $registry->after('record.create', [
        new class implements \Veloquent\Core\Domain\Hooks\Contracts\HookPipe {
            public function handle(HookPayload $payload, Closure $next): mixed {
                throw new \RuntimeException('After Hook Failed');
            }
        }
    ]);

    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->withArgs(fn($message) => str_contains($message, 'After Hook Failed'));

    $runner = new HookRunner($registry);
    $collection = new Collection();
    $payload = new HookPayload('record.created', $collection);

    $result = $runner->run($payload);

    expect($result)->toBeInstanceOf(HookPayload::class);
});
