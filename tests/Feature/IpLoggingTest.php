<?php

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

it('logs IP address when enabled', function () {
    config(['velo.logs.ip_enabled' => true]);

    Log::shouldReceive('info')
        ->with('HTTP_REQUEST', Mockery::on(function ($context) {
            return array_key_exists('ip', $context) && $context['ip'] === '127.0.0.1';
        }))
        ->once();

    $request = Request::create('/api/test', 'GET');
    $request->server->set('REMOTE_ADDR', '127.0.0.1');
    $response = new Response;

    Event::dispatch(new RequestHandled($request, $response));
});

it('does not log IP address when disabled', function () {
    config(['velo.logs.ip_enabled' => false]);

    Log::shouldReceive('info')
        ->with('HTTP_REQUEST', Mockery::on(function ($context) {
            return array_key_exists('ip', $context) && $context['ip'] === null;
        }))
        ->once();

    $request = Request::create('/api/test', 'GET');
    $request->server->set('REMOTE_ADDR', '127.0.0.1');
    $response = new Response;

    Event::dispatch(new RequestHandled($request, $response));
});
