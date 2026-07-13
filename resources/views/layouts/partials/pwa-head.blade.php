@props([
    'app' => 'kasir',
    'title' => null,
])

@php
    $manifestApp = in_array($app, ['kasir', 'order'], true) ? $app : 'kasir';
    $appTitle = $title ?? match ($manifestApp) {
        'order' => config('pos.shop_name', 'Coffee & Kitchen').' — Pesan',
        default => config('pos.shop_name', 'Coffee & Kitchen').' — Kasir',
    };
@endphp

<link rel="manifest" href="{{ route('pwa.manifest', $manifestApp) }}">
@include('layouts.partials.favicon')
<meta name="mobile-web-app-capable" content="yes">
<meta name="application-name" content="{{ $appTitle }}">
<meta name="apple-mobile-web-app-title" content="{{ $appTitle }}">
