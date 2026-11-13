{{-- One sidebar link. Reads `collapsed` from the sidebar's Alpine scope in layouts/app.blade.php. --}}
@props(['href', 'icon', 'active' => false, 'hint' => null])
@php($label = trim((string) $slot))
<a href="{{ $href }}" @if($active) aria-current="page" @endif
   :aria-label="collapsed ? @js($label) : null"
   {{ $attributes->class([
        'group relative flex items-center gap-3 rounded-lg px-3 py-2 text-[15px] font-medium transition',
        'bg-ink-800 text-fg' => $active,
        'text-ink-300 hover:bg-ink-850 hover:text-fg' => ! $active,
   ]) }}
   :class="collapsed ? 'lg:justify-center lg:px-0' : ''">

    <span class="relative shrink-0">
        <x-icon :name="$icon" @class(['h-5 w-5', 'text-brand-400' => $active, 'text-ink-400 group-hover:text-ink-200' => ! $active]) />
        @if($hint)<span class="absolute -right-0.5 -top-0.5 h-2 w-2 rounded-full bg-amber-400 ring-2 ring-ink-900" :class="collapsed ? '' : 'lg:hidden'"></span>@endif
    </span>

    <span class="min-w-0 flex-1 leading-tight" :class="collapsed ? 'lg:hidden' : ''">
        <span class="block truncate">{{ $slot }}</span>
        @if($hint)<span class="block truncate text-xs font-normal text-amber-400">{{ $hint }}</span>@endif
    </span>

    {{-- name tooltip, only while the menu is hidden to icons --}}
    <span class="pointer-events-none absolute left-full z-[60] ml-2 hidden whitespace-nowrap rounded-md border border-ink-600 bg-ink-800 px-2.5 py-1.5 text-xs font-medium text-fg shadow-xl"
          :class="collapsed ? 'lg:group-hover:block' : ''">{{ $slot }}@if($hint) <span class="text-amber-400">· {{ $hint }}</span>@endif</span>
</a>
