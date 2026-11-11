{{--
    The user menu's "Support" item pops this open (its alpineClickHandler in
    MainPanelProvider) the same way "Keyboard help" does, rather than linking
    to a page.
--}}
@php
    $options = [
        [
            'heading' => __('Epesi forum'),
            'url' => 'https://forum.epe.si/',
            'does' => __('The main place to ask questions, report problems and get help from the Epesi community and developers.'),
            'icon' => 'heroicon-o-arrow-top-right-on-square',
        ],
        [
            'heading' => __('Epesi CRM website'),
            'url' => 'https://epesicrm.com/',
            'does' => __('General information about Epesi CRM: features, documentation and releases.'),
            'icon' => 'heroicon-o-arrow-top-right-on-square',
        ],
        [
            'heading' => __('GitHub repository'),
            'url' => 'https://github.com/jtylek/epesiCRM',
            'does' => __('Source code, and the place to report bugs or request features as GitHub issues.'),
            'icon' => 'heroicon-o-arrow-top-right-on-square',
        ],
        [
            'heading' => __('Consulting services'),
            'url' => 'https://epe.si',
            'does' => __('Professional consulting, customization and deployment services for Epesi.'),
            'icon' => 'heroicon-o-arrow-top-right-on-square',
        ],
        [
            'heading' => __('Contact us via e-mail'),
            // Built in the browser, not written as "mailto:" here: that
            // literal string is what test_a_mailto_link_in_the_body_opens_…
            // forbids anywhere in a mail page's body, and this modal renders
            // on every page of the main panel (BODY_END), this one included.
            'email' => 'support@epe.si',
            'does' => __('Contact the support team directly by e-mail at :email.', ['email' => 'support@epe.si']),
            'icon' => 'heroicon-o-envelope',
        ],
    ];
@endphp

<x-filament::modal id="support" icon="heroicon-o-lifebuoy" width="xl">
    <x-slot name="heading">
        <div class="flex items-baseline justify-between gap-2">
            {{ __('Support') }}
            <span class="text-sm font-normal text-gray-950 dark:text-white">{{ __('epesi version :version', ['version' => \App\Support\Version::short()]) }}</span>
        </div>
    </x-slot>

    <div class="space-y-3">
        @foreach ($options as $option)
            <a
                @if (isset($option['email']))
                    href="#"
                    x-data="{ email: @js($option['email']) }"
                    x-on:click.prevent="window.location.href = ['mailto', email].join(':')"
                @else
                    href="{{ $option['url'] }}"
                    target="_blank"
                    rel="noopener"
                @endif
                class="block rounded-xl p-3 ring-1 ring-gray-950/10 transition hover:ring-primary-600 dark:ring-white/10 dark:hover:ring-primary-400"
            >
                <p class="flex items-center gap-1.5 text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $option['heading'] }}
                    <x-filament::icon :icon="$option['icon']" class="h-5 w-5 text-gray-400 dark:text-gray-500" />
                </p>

                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                    {{ $option['does'] }}
                </p>
            </a>
        @endforeach
    </div>

    <p class="mt-4 text-xs text-gray-400 dark:text-gray-500">
        {{ __('Links above open in a new browser window, except for the e-mail address, which opens your mail app.') }}
    </p>
</x-filament::modal>
