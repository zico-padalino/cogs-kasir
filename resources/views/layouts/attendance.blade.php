<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f766e">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>@yield('title', 'Absensi') — {{ config('pos.shop_name', 'POS') }}</title>
    @include('layouts.partials.favicon')
    @hasSection('vite')
        @yield('vite')
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="attendance-page">
    <div class="attendance-page-glow" aria-hidden="true"></div>
    <div class="attendance-page-inner">
        @yield('content')
    </div>
</body>
</html>
