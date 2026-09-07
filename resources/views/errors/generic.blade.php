@php
    $status = $code ?? (isset($exception) ? \App\Support\ErrorPages::statusFrom($exception) : 500);
@endphp
@include('errors.page', ['code' => $status])
