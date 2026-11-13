<x-app-layout title="My account">
    <x-page-header title="My account" subtitle="Change your name, email or password." />

    <div class="max-w-2xl space-y-4">
        <div class="card card-pad">
            @include('profile.partials.update-profile-information-form')
        </div>
        <div class="card card-pad">
            @include('profile.partials.update-password-form')
        </div>
    </div>
</x-app-layout>
