{{--
  Mobile sticky topbar (Admin / COGS / Kasir).
  Props via @include:
    - $moduleLabel (string|null)  chip di kanan, contoh: Admin / COGS
    - $fallbackHeading (string)   default judul
    - $subtitle (string|null)     paksa subtitle; jika null → @yield('subheading') lalu $defaultSubtitle
    - $defaultSubtitle (string|null)
--}}
@php
    $moduleLabel = $moduleLabel ?? null;
    $fallbackHeading = $fallbackHeading ?? 'Menu';
    $defaultSubtitle = $defaultSubtitle ?? null;
@endphp
<div class="mobile-topbar shrink-0 md:hidden">
    <div class="mobile-topbar-inner">
        <button type="button" class="mobile-menu-btn" data-mobile-menu-toggle aria-label="Buka menu" aria-controls="mobile-sidebar">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        <div class="mobile-topbar-copy">
            <p class="mobile-topbar-title">@yield('heading', $fallbackHeading)</p>
            @hasSection('subheading')
                <p class="mobile-topbar-subtitle">@yield('subheading')</p>
            @elseif (! empty($subtitle))
                <p class="mobile-topbar-subtitle">{{ $subtitle }}</p>
            @elseif (! empty($defaultSubtitle))
                <p class="mobile-topbar-subtitle">{{ $defaultSubtitle }}</p>
            @endif
        </div>

        @hasSection('mobile_topbar_actions')
            <div class="mobile-topbar-actions">
                @yield('mobile_topbar_actions')
            </div>
        @elseif (! empty($moduleLabel))
            <span class="mobile-topbar-badge">{{ $moduleLabel }}</span>
        @endif
    </div>
</div>
