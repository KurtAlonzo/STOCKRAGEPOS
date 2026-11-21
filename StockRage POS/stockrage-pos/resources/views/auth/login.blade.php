<x-guest-layout>
    <h1 class="text-lg font-bold text-fg">StockRage POS</h1>

    <form method="POST" action="{{ route('login.demo') }}" class="mt-6">
        @csrf

        <x-primary-button class="w-full py-2.5">Fill demo credentials</x-primary-button>
        <x-input-error :messages="$errors->get('demo')" class="mt-2" />
    </form>
</x-guest-layout>
