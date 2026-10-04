<x-authentication::layouts.card :title="__('authentication::messages.verify_email_title')">
    <div class="space-y-4 text-center">
        <x-authentication::header
            :title="__('authentication::messages.verify_email_title')"
            :subtitle="__('authentication::messages.verify_email_subtitle')"
        />

        @if (session('status'))
            <x-authentication::feedback.alert type="success" :message="session('status')" />
        @endif

        <form method="POST" action="{{ route('authentication.verification.send') }}">
            @csrf
            <x-authentication::forms.button type="submit" variant="primary">
                {{ __('authentication::messages.verify_email_resend') }}
            </x-authentication::forms.button>
        </form>
    </div>
</x-authentication::layouts.card>
