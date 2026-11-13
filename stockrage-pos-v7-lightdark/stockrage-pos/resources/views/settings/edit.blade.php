<x-app-layout title="Settings">
    <x-page-header title="Settings" subtitle="Basic shop settings." />

    <form method="POST" action="{{ route('settings.update') }}" class="card card-pad max-w-xl space-y-5">
        @csrf @method('PUT')

        <div>
            <label class="label" for="business_name">Business name</label>
            <input id="business_name" name="business_name" value="{{ old('business_name', $businessName) }}" required maxlength="60" class="input">
            <p class="hint">Shown under the logo in the sidebar.</p>
            <x-input-error :messages="$errors->get('business_name')" class="mt-1.5" />
        </div>

        <div>
            <label class="label" for="default_low_stock_threshold">Default low stock alert</label>
            <input id="default_low_stock_threshold" type="number" min="0" step="1" name="default_low_stock_threshold" value="{{ old('default_low_stock_threshold', $defaultThreshold) }}" required class="input num">
            <p class="hint">Pre-filled when you add a new product. Each product can still have its own alert level.</p>
            <x-input-error :messages="$errors->get('default_low_stock_threshold')" class="mt-1.5" />
        </div>

        <div>
            <label class="label" for="receipt_footer">Receipt footer</label>
            <input id="receipt_footer" name="receipt_footer" value="{{ old('receipt_footer', $receiptFooter) }}" maxlength="120" class="input" placeholder="Thank you! Come again.">
            <p class="hint">The message printed at the bottom of every receipt.</p>
            <x-input-error :messages="$errors->get('receipt_footer')" class="mt-1.5" />
        </div>

        <fieldset x-data="{ theme: @js(old('theme_color', $theme)) }" x-effect="document.documentElement.dataset.theme = theme">
            <legend class="label">Theme color</legend>
            <div class="mt-2 flex flex-wrap gap-3">
                @foreach($themes as $key => [$label, $hex])
                    <label class="cursor-pointer" title="{{ $label }}">
                        <input type="radio" name="theme_color" value="{{ $key }}" x-model="theme" class="peer sr-only" @checked(old('theme_color', $theme) === $key)>
                        <span class="flex h-10 w-10 items-center justify-center rounded-full ring-2 ring-transparent ring-offset-2 ring-offset-ink-900 transition peer-checked:ring-white peer-focus-visible:ring-white/70" style="background: {{ $hex }}">
                            <svg x-show="theme === '{{ $key }}'" class="h-5 w-5 text-fg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg>
                            <span class="sr-only">{{ $label }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <p class="hint">Changes buttons, highlights and charts. You see it live; press Save to keep it. Low-stock and error warnings always stay red.</p>
            <x-input-error :messages="$errors->get('theme_color')" class="mt-1.5" />
        </fieldset>

        <div class="flex justify-end border-t border-ink-700 pt-5">
            <button class="btn-primary">Save settings</button>
        </div>
    </form>
</x-app-layout>
