{{-- Placeholder picture for products that have no photo yet: three takoyaki balls on a plate. --}}
<svg {{ $attributes->merge(['class' => 'h-full w-full']) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 90" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Takoyaki">
    <rect width="120" height="90" fill="#1c1c21"/>
    <circle cx="96" cy="14" r="30" style="fill: rgb(var(--brand-600))" opacity=".12"/>
    <ellipse cx="60" cy="66" rx="44" ry="10" fill="#27272d"/>
    <ellipse cx="60" cy="63" rx="40" ry="8.5" fill="#383840"/>
    {{-- balls --}}
    <circle cx="40" cy="52" r="15" fill="#c98a3d"/>
    <circle cx="80" cy="52" r="15" fill="#c98a3d"/>
    <circle cx="60" cy="42" r="17" fill="#d99a4a"/>
    <circle cx="55" cy="36" r="5" fill="#f0c27a" opacity=".55"/>
    <circle cx="35" cy="47" r="3.5" fill="#f0c27a" opacity=".45"/>
    <circle cx="75" cy="47" r="3.5" fill="#f0c27a" opacity=".45"/>
    {{-- sauce --}}
    <path d="M45 44q7-6 14 0t14 0" fill="none" stroke="#5b2a12" stroke-width="3.2" stroke-linecap="round"/>
    <path d="M28 56q6-5 12 0t12 0" fill="none" stroke="#5b2a12" stroke-width="2.8" stroke-linecap="round"/>
    <path d="M68 57q6-5 12 0t12 0" fill="none" stroke="#5b2a12" stroke-width="2.8" stroke-linecap="round"/>
    {{-- mayo --}}
    <path d="M47 40l12 -4M62 46l12 -4M30 51l10 -3M72 52l10 -3" stroke="#fff6e0" stroke-width="1.6" stroke-linecap="round"/>
    {{-- bonito flakes --}}
    <path d="M56 33l3-3M63 32l2-3M60 38l3-2M38 45l2-3M82 46l2-3" stroke="#f3b9a0" stroke-width="1.4" stroke-linecap="round"/>
</svg>
