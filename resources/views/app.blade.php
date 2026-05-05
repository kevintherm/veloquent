<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('vendor/velo/favicon.ico') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('vendor/velo/logo.svg') }}">
    <meta name="velo-config" content="{{ json_encode([
    'api_prefix' => config('velo.api_prefix', 'api'),
    'admin_prefix' => config('velo.admin_prefix', 'admin'),
    'logo_url' => asset('vendor/velo/logo.svg'),
    'is_demo' => env('VELO_IS_DEMO', false),
    'demo_creds' => [
        'email' => env('VELO_DEMO_EMAIL', 'demo@velophp.com'),
        'password' => env('VELO_DEMO_PASSWORD', 'demo@123123'),
    ],
    'realtime' => [
        'type' => config('broadcasting.default'),
        'pusher' => [
            'key' => config('broadcasting.connections.pusher.key'),
            'cluster' => config('broadcasting.connections.pusher.options.cluster'),
            'host' => config('broadcasting.connections.pusher.options.host'),
            'port' => config('broadcasting.connections.pusher.options.port'),
            'scheme' => config('broadcasting.connections.pusher.options.scheme'),
        ],
        'reverb' => [
            'key' => config('broadcasting.connections.reverb.key'),
            'host' => config('broadcasting.connections.reverb.options.host'),
            'port' => config('broadcasting.connections.reverb.options.port'),
            'scheme' => config('broadcasting.connections.reverb.options.scheme'),
        ],
    ]
]) }}">


    @php
        $useDevServer = app()->isLocal() && file_exists(public_path('hot'));
    @endphp

    @vite(
        $useDevServer
        ? (file_exists(base_path('core'))
            ? ['core/resources/css/app.css', 'core/resources/js/app.js']
            : ['vendor/veloquent/core/resources/css/app.css', 'vendor/veloquent/core/resources/js/app.js'])
        : ['resources/css/app.css', 'resources/js/app.js'],
        $useDevServer ? null : 'vendor/velo'
    )
</head>

<body class="antialiased overflow-y-hidden">
    <div id="app"></div>
</body>

</html>