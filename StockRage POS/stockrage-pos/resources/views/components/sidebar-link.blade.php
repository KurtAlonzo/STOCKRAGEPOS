{{-- One sidebar link. The icon-only look comes from the `rail:` variant (html[data-sidebar=collapsed]), not from JavaScript. --}}
@props(['href', 'icon', 'active' => false, 'hint' => null])
<a href="{{ $href }}" @if($active) aria-current="page" @endif title="{{ trim((string) $slot) }}"
   {{ $attributes->class([
        'group relative flex items-center gap-3 rounded-lg px-3 py-2 text-[15px] font-medium transition rail:justify-center rail:px-0',
        'bg-ink-800 text-fg' => $active,
        'text-ink-300 hover:bg-ink-850 hover:text-fg' => ! $active,
   ]) }}>

    <span class="relative shrink-0">
        <x-icon :name="$icon" @class(['h-5 w-5', 'text-brand-400' => $active, 'text-ink-400 group-hover:text-ink-200' => ! $active]) />
        @if($hint)<span class="absolute -right-0.5 -top-0.5 hidden h-2 w-2 rounded-full bg-amber-400 ring-2 ring-ink-900 rail:block"></span>@endif
    </span>

    <span class="min-w-0 flex-1 leading-tight rail:hidden">
        <span class="block truncate">{{ $slot }}</span>
        @if($hint)<span class="block truncate text-xs font-normal text-amber-400">{{ $hint }}</span>@endif
    </span>
</a>
