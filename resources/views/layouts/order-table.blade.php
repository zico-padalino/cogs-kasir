<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#5c4033">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Menu Meja') — Pemesanan</title>
    @include('layouts.partials.pwa-head', ['app' => 'order'])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="order-table-body min-h-screen bg-[#f6f1ea] font-sans text-slate-900 antialiased">
    @include('layouts.partials.pwa-install-banner', ['app' => 'order'])
    @yield('content')
</body>
</html>
