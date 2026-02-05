<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    @routes
    @vite(['resources/frontend/app.js','resources/frontend/assets/css/app.scss'])
    @inertiaHead
    <meta name="csrf-token" content="{{ csrf_token() }}" id="csrf_token">
</head>
<body class="antialiased">
@inertia
</body>
</html>
