@props(['title', 'subtitle' => null])
<div {{ $attributes->class('mb-6 flex flex-wrap items-end justify-between gap-4 print:hidden') }}>
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-fg">{{ $title }}</h1>
        @if($subtitle)<p class="mt-1 text-sm text-ink-400">{{ $subtitle }}</p>@endif
    </div>
    @if(trim($slot))
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
