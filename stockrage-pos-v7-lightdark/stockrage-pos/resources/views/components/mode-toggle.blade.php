{{-- Light / dark switch. `window.toggleMode()` is defined in the <head> script of the layouts. --}}
@props(['label' => true])
<button type="button" onclick="window.toggleMode()"
        {{ $attributes->class('flex items-center gap-3 rounded-lg text-sm font-medium text-ink-400 transition hover:bg-ink-850 hover:text-fg focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500') }}
        aria-label="Switch between light and dark mode" title="Switch between light and dark mode">
    <svg class="hidden h-5 w-5 shrink-0 dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
    <svg class="h-5 w-5 shrink-0 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/></svg>
    @if($label)<span :class="collapsed ? 'lg:hidden' : ''"><span class="dark:hidden">Dark mode</span><span class="hidden dark:inline">Light mode</span></span>@endif
</button>
