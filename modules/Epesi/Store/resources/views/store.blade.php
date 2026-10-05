@php
    $setting = $this->setting();
    $newer = app(\Epesi\Modules\Store\Services\Registration::class)->newerVersion();
@endphp

<x-filament-panels::page>
    @if ($setting->isRegistered() && $setting->latest_core_security && $newer)
        <x-filament::callout color="danger" icon="heroicon-o-shield-exclamation" :heading="__('Security update available')">
            <x-slot name="description">
                {{ __('epesi :version fixes a security issue; this installation runs :current. Update with the button above.', ['version' => $newer, 'current' => \App\Support\Version::current()]) }}
            </x-slot>
        </x-filament::callout>
    @endif

    @if ($setting->registration_status === \Epesi\Modules\Store\Models\StoreSetting::REVOKED)
        <x-filament::callout color="danger" icon="heroicon-o-no-symbol" :heading="__('Registration revoked')">
            <x-slot name="description">{{ __('The Epesi Store revoked the registration of this installation. Contact the Epesi team if you think this is a mistake.') }}</x-slot>
        </x-filament::callout>
    @elseif ($setting->isRegistered() && $setting->url_mismatch)
        <x-filament::callout color="warning" icon="heroicon-o-arrows-right-left" :heading="__('This installation has moved')">
            <x-slot name="description">
                @if ($setting->transfer_pending)
                    {{ __('A confirmation link was sent to :email. After it is clicked, click Refresh.', ['email' => $setting->registered_email]) }}
                @else
                    {{ __('The licence is registered to :old, but this installation is at :new. Updates and the Store work again once the licence is moved here.', ['old' => $setting->registered_url, 'new' => \Epesi\Modules\Store\Services\StoreClient::installationUrl()]) }}
                @endif
            </x-slot>
            <x-slot name="footer">{{ $this->transferAction }}</x-slot>
        </x-filament::callout>
    @elseif ($setting->isPending())
        <x-filament::callout color="warning" icon="heroicon-o-envelope" :heading="__('Registration pending')">
            <x-slot name="description">
                @if ($setting->registered_email)
                    {{ __('Confirm the link sent to :email, then click Refresh. The licence key is issued as soon as the e-mail is confirmed.', ['email' => $setting->registered_email]) }}
                @else
                    {{ __('Finish the registration form on the Epesi Store, then confirm the e-mail it sends you.') }}
                @endif
            </x-slot>
            <x-slot name="footer">{{ $this->registerAction }}</x-slot>
        </x-filament::callout>
    @elseif (! $setting->isRegistered())
        <x-filament::section :heading="__('Register Epesi')" icon="heroicon-o-check-badge">
            <div class="space-y-3 text-sm">
                <p>{{ __('epesi is free and open source (MIT) and works without registration. Registration is free too, and it unlocks:') }}</p>
                <ul class="list-disc ps-5 space-y-1">
                    <li><b>{{ __('Automatic updates') }}</b> — {{ __('a daily check for new epesi versions, with a notice to the administrators and a one-click update.') }}</li>
                    <li><b>{{ __('The Epesi Store') }}</b> — {{ __('browse and install modules, free modules included.') }}</li>
                    <li><b>{{ __('Security notices') }}</b> — {{ __('security releases are flagged so you see them first.') }}</li>
                    <li><b>{{ __('A licence key') }}</b> — {{ __('issued automatically; Premium modules you buy later are tied to it.') }}</li>
                </ul>
                <p class="text-gray-500 dark:text-gray-400">{{ __('You need an e-mail address, a first and a last name. Your data is never sold or shared with third parties and is used only to tell you about new epesi versions.') }}</p>
                <div>{{ $this->registerAction }}</div>
            </div>
        </x-filament::section>
    @endif

    @if ($setting->isRegistered() && ! $setting->url_mismatch)
        {{ $this->table }}
    @endif
</x-filament-panels::page>
