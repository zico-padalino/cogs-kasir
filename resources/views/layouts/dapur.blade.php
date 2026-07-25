<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#5c4033">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dapur') — POS</title>
    @include('layouts.partials.pwa-head', ['app' => 'kasir'])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="kds-body min-h-screen font-sans text-slate-900 antialiased"
    data-dapur-board
    data-dapur-poll-url="{{ route('kasir.dapur.poll') }}"
    data-dapur-poll-interval="{{ $pollInterval ?? config('pos.notifications.poll_interval_seconds', 5) }}"
    data-dapur-fingerprint="{{ $kitchenFingerprint ?? '' }}"
    data-kasir-pin-status-url="{{ route('kasir.pin.status') }}"
    data-kasir-pin-touch-url="{{ route('kasir.pin.touch') }}"
    data-kasir-pin-unlock-url="{{ route('kasir.pin.unlock') }}"
>
    @yield('content')
</body>
</html>
