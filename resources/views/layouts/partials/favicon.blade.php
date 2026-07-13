@php
    $faviconUrl = \App\Support\ShopSettings::faviconUrl();
    $appleIconUrl = \App\Support\ShopSettings::appleTouchIconUrl();
    $faviconType = str_ends_with(strtolower(parse_url($faviconUrl, PHP_URL_PATH) ?? ''), '.webp')
        ? 'image/webp'
        : (str_ends_with(strtolower(parse_url($faviconUrl, PHP_URL_PATH) ?? ''), '.jpg')
            || str_ends_with(strtolower(parse_url($faviconUrl, PHP_URL_PATH) ?? ''), '.jpeg')
                ? 'image/jpeg'
                : 'image/png');
@endphp
<link rel="icon" href="{{ $faviconUrl }}" type="{{ $faviconType }}" sizes="any">
<link rel="shortcut icon" href="{{ $faviconUrl }}">
<link rel="apple-touch-icon" href="{{ $appleIconUrl }}">
