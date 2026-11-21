<x-guest-layout>
    <h1 class="text-lg font-bold text-fg">Welcome back</h1>
    <p class="mt-1 mb-6 text-sm text-ink-400">Log in to open the register.</p>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Password" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="flex items-center gap-2 text-sm text-ink-300">
            <input id="remember_me" type="checkbox" name="remember" class="rounded border-ink-600 bg-ink-850 text-brand-600 focus:ring-brand-500 focus:ring-offset-ink-900">
            Remember me
        </label>

        <button type="button" class="btn-secondary w-full py-2.5" x-data
                data-demo-email="{{ config('pos.admin_email') }}"
                data-demo-password="{{ config('pos.admin_password') }}"
                x-on:click="$el.form.elements.email.value = $el.dataset.demoEmail;
                            $el.form.elements.password.value = $el.dataset.demoPassword">
            Fill demo credentials
        </button>

        <x-primary-button class="w-full py-2.5">Log in</x-primary-button>
    </form>
</x-guest-layout>
