<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f766e">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Absensi') — {{ config('pos.shop_name', 'POS') }}</title>
    @include('layouts.partials.favicon')
    @hasSection('vite')
        @yield('vite')
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/attendance-face.js'])
    @endif
</head>
<body class="login-page">
    <div class="login-glow" aria-hidden="true"></div>
    @yield('content')
</body>
</html>
