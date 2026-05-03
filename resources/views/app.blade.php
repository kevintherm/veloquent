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
    ]) }}">


    @vite(
        app()->isLocal() 
            ? ['core/resources/css/app.css', 'core/resources/js/app.js'] 
            : ['resources/css/app.css', 'resources/js/app.js'],
        app()->isLocal() ? null : 'vendor/velo'
    )
</head>

<body class="antialiased overflow-y-hidden">
    <div id="app"></div>
</body>

</html>