<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#4f46e5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Menu Meja') — Pemesanan</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="order-table-body min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
    @yield('content')
</body>
</html>
