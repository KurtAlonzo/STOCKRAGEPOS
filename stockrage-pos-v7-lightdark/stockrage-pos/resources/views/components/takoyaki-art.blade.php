{{-- Picture for products with no photo: a real photo from public/images/menu/ if you added one, otherwise a drawing. --}}
@props(['kind' => null, 'name' => null])
@php
    $kind ??= \App\Support\MenuArt::kind((string) $name);
    $photo = \App\Support\MenuArt::photoUrl($kind);
    $u = 'tk'.substr(md5(uniqid('', true)), 0, 6); // unique ids, so several drawings can share a page
@endphp
@if($photo)
    <img src="{{ $photo }}" alt="Takoyaki" aria-label="Takoyaki" {{ $attributes->except(['kind', 'name'])->merge(['class' => 'h-full w-full object-cover']) }}>
@else
<svg {{ $attributes->except(['kind', 'name'])->merge(['class' => 'h-full w-full']) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 90" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Takoyaki">
    <defs>
        <linearGradient id="{{ $u }}bg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2b2a31"/><stop offset="1" stop-color="#17171b"/></linearGradient>
        <radialGradient id="{{ $u }}glow" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#ffb347" stop-opacity=".28"/><stop offset="1" stop-color="#ffb347" stop-opacity="0"/></radialGradient>
        <linearGradient id="{{ $u }}tray" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#e2bd88"/><stop offset="1" stop-color="#a97a45"/></linearGradient>
        <radialGradient id="{{ $u }}ball" cx=".38" cy=".3" r=".85"><stop offset="0" stop-color="#f6c872"/><stop offset=".55" stop-color="#d08a35"/><stop offset="1" stop-color="#8c4f17"/></radialGradient>
        <radialGradient id="{{ $u }}sauce" cx=".4" cy=".25" r=".9"><stop offset="0" stop-color="#7a3a14"/><stop offset="1" stop-color="#3a1807"/></radialGradient>
        <g id="{{ $u }}b">
            <circle r="13" fill="url(#{{ $u }}ball)"/>
            <path d="M-12.3 -3.5C-10 -13 8 -14.5 12.4 -3C6 -7 -6 -6 -12.3 -3.5Z" fill="url(#{{ $u }}sauce)" opacity=".92"/>
            <path d="M-11 4C-7 12 8 13 11 4C6 8 -5 8 -11 4Z" fill="url(#{{ $u }}sauce)" opacity=".7"/>
            <ellipse cx="-4.5" cy="-8" rx="4.5" ry="1.8" fill="#fff" opacity=".4" transform="rotate(-18 -4.5 -8)"/>
        </g>
    </defs>

    <rect width="120" height="90" fill="url(#{{ $u }}bg)"/>
    <ellipse cx="60" cy="52" rx="52" ry="34" fill="url(#{{ $u }}glow)"/>

    {{-- paper boat tray --}}
    <ellipse cx="60" cy="79" rx="46" ry="5" fill="#000" opacity=".35"/>
    <path d="M10 56Q60 68 110 56L101 78Q60 86 19 78Z" fill="url(#{{ $u }}tray)"/>
    <path d="M10 56Q60 68 110 56" fill="none" stroke="#f3d9a8" stroke-width="1.2" opacity=".7"/>
    <path d="M24 66Q60 74 96 66" fill="none" stroke="#8e6232" stroke-width=".8" opacity=".5"/>

    {{-- balls: back, middle, front --}}
    <use href="#{{ $u }}b" transform="translate(42 44) scale(.9)"/>
    <use href="#{{ $u }}b" transform="translate(78 44) scale(.9)"/>
    <use href="#{{ $u }}b" transform="translate(60 39)"/>
    <use href="#{{ $u }}b" transform="translate(32 58) scale(.95)"/>
    <use href="#{{ $u }}b" transform="translate(60 61) scale(1.02)"/>
    <use href="#{{ $u }}b" transform="translate(88 58) scale(.95)"/>
    @if($kind === 'overload')
        <use href="#{{ $u }}b" transform="translate(16 62) scale(.7)"/>
        <use href="#{{ $u }}b" transform="translate(104 62) scale(.7)"/>
    @endif

    @if($kind === 'bacon')
        {{-- crispy bacon strips + melted cheese --}}
        <path d="M30 46q8 -6 16 0t16 0t16 0" fill="none" stroke="#f6c33b" stroke-width="3.2" stroke-linecap="round" opacity=".95"/>
        <path d="M26 60q9 -6 18 0t18 0t18 0" fill="none" stroke="#f6c33b" stroke-width="3" stroke-linecap="round" opacity=".95"/>
        <path d="M34 40l22 12" stroke="#7d2418" stroke-width="5.5" stroke-linecap="round"/><path d="M34 40l22 12" stroke="#d9654a" stroke-width="2.4" stroke-linecap="round" stroke-dasharray="5 4"/>
        <path d="M66 36l24 11" stroke="#7d2418" stroke-width="5.5" stroke-linecap="round"/><path d="M66 36l24 11" stroke="#d9654a" stroke-width="2.4" stroke-linecap="round" stroke-dasharray="5 4"/>
        <path d="M44 58l18 9" stroke="#7d2418" stroke-width="5" stroke-linecap="round"/><path d="M44 58l18 9" stroke="#d9654a" stroke-width="2.2" stroke-linecap="round" stroke-dasharray="5 4"/>
    @elseif($kind === 'mix')
        {{-- crab stick, corn, octopus, spring onion --}}
        <path d="M33 38l9 -3M70 36l9 3M48 56l9 -3M76 62l9 3" stroke="#e8553f" stroke-width="3.6" stroke-linecap="round"/>
        <path d="M33 38l9 -3M70 36l9 3M48 56l9 -3M76 62l9 3" stroke="#fff4ec" stroke-width="1.1" stroke-linecap="round" stroke-dasharray="2 3"/>
        <circle cx="52" cy="40" r="2" fill="#f6c33b"/><circle cx="84" cy="46" r="2" fill="#f6c33b"/><circle cx="28" cy="58" r="2" fill="#f6c33b"/><circle cx="64" cy="70" r="2" fill="#f6c33b"/><circle cx="96" cy="60" r="2" fill="#f6c33b"/>
        <circle cx="62" cy="33" r="2.8" fill="#caa0e0"/><circle cx="38" cy="52" r="2.8" fill="#caa0e0"/><circle cx="90" cy="66" r="2.8" fill="#caa0e0"/>
        <path d="M44 44l4 3M58 50l5 1M72 52l4 3M30 66l4 -2M100 54l4 2" stroke="#6bbf59" stroke-width="2" stroke-linecap="round"/>
    @elseif($kind === 'overload')
        {{-- heaps of cheese, mayo, sauce and nori --}}
        <path d="M18 48q10 9 22 2t22 2t22 -2t18 3" fill="none" stroke="#f6c33b" stroke-width="3.6" stroke-linecap="round"/>
        <path d="M14 62q10 7 22 1t22 1t22 -1t18 2" fill="none" stroke="#f6c33b" stroke-width="3.2" stroke-linecap="round"/>
        <path d="M24 36l8 4M46 32l8 5M68 33l8 5M88 40l7 3M12 56l7 2" stroke="#fffaf0" stroke-width="2.4" stroke-linecap="round"/>
        <path d="M32 54l8 3M54 66l8 3M78 56l8 3M96 66l6 2" stroke="#fffaf0" stroke-width="2.2" stroke-linecap="round"/>
        <path d="M38 42h4M62 34h4M84 42h4M48 52h4M70 58h4M26 60h3M92 60h4M58 72h4" stroke="#1f3a28" stroke-width="2.8" stroke-linecap="round"/>
    @elseif($kind === 'cheese')
        {{-- lots of melted cheese sauce --}}
        <path d="M22 46q10 9 20 2t20 2t20 -2t16 3" fill="none" stroke="#f6c33b" stroke-width="4" stroke-linecap="round"/>
        <path d="M18 60q10 7 20 1t20 1t20 -1t18 2" fill="none" stroke="#f6c33b" stroke-width="3.6" stroke-linecap="round"/>
        <path d="M44 38q8 8 16 2" fill="none" stroke="#ffd966" stroke-width="3" stroke-linecap="round"/>
        <path d="M36 44h3M62 36h3M82 46h3M52 62h3M88 60h3" stroke="#1f3a28" stroke-width="2.4" stroke-linecap="round"/>
    @elseif($kind === 'crab')
        {{-- crab stick pieces on top --}}
        <path d="M34 40l11 -4M66 34l11 4M80 46l9 -3M44 58l10 -3M74 62l10 -3" stroke="#e8553f" stroke-width="4" stroke-linecap="round"/>
        <path d="M34 40l11 -4M66 34l11 4M80 46l9 -3M44 58l10 -3M74 62l10 -3" stroke="#fff4ec" stroke-width="1.3" stroke-linecap="round" stroke-dasharray="2.5 3"/>
        <path d="M30 50q8 -4 16 0t16 0" fill="none" stroke="#fffaf0" stroke-width="2" stroke-linecap="round"/>
        <path d="M36 44h3M62 38h3M84 52h3M54 66h3" stroke="#1f3a28" stroke-width="2.4" stroke-linecap="round"/>
    @elseif($kind === 'octobits')
        {{-- bite-size octopus pieces --}}
        <circle cx="38" cy="36" r="3.2" fill="#b24a62"/><circle cx="62" cy="31" r="3.2" fill="#b24a62"/><circle cx="84" cy="38" r="3.2" fill="#b24a62"/>
        <circle cx="28" cy="52" r="3" fill="#b24a62"/><circle cx="96" cy="54" r="3" fill="#b24a62"/><circle cx="60" cy="52" r="3.2" fill="#b24a62"/>
        <circle cx="38" cy="36" r="1.2" fill="#f3c9d2"/><circle cx="62" cy="31" r="1.2" fill="#f3c9d2"/><circle cx="84" cy="38" r="1.2" fill="#f3c9d2"/><circle cx="60" cy="52" r="1.2" fill="#f3c9d2"/>
        <path d="M44 44l6 2M70 44l6 2M30 62l5 -2" stroke="#fffaf0" stroke-width="2" stroke-linecap="round"/>
    @else
        <path d="M30 40l10 -3M58 34l10 3M74 40l10 -3M40 56l9 -3M72 60l10 -3" stroke="#fffaf0" stroke-width="2" stroke-linecap="round"/>
        <path d="M36 44h3M62 38h3M80 48h3M52 62h3M88 62h3" stroke="#1f3a28" stroke-width="2.4" stroke-linecap="round"/>
    @endif

    {{-- bonito flakes --}}
    <g fill="none" stroke="#f1b79a" stroke-width="1.1" stroke-linecap="round" opacity=".95">
        <path d="M54 30q3 -4 6 0M62 28q3 -4 5 0M40 38q2 -4 5 -1M82 36q3 -4 5 0M30 50q2 -4 5 -1M92 52q2 -4 5 -1M58 56q3 -4 6 0"/>
    </g>
    {{-- toothpick --}}
    <path d="M70 76L96 44" stroke="#e8d3a6" stroke-width="1.3" stroke-linecap="round"/>
</svg>
@endif
