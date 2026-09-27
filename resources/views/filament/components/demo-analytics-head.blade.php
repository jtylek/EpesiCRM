{{--
    Google Analytics on the demo (App\Filament\Support\DemoAnalytics), as on
    epesicrm.com: Google's tag always loads and always configures, but
    analytics_storage stays denied (no _ga cookies, only a cookieless
    consent-mode ping) until the visitor has accepted, here or on the site.
--}}
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }

    window.epesiAnalytics = {
        choice() {
            try { return localStorage.getItem(@js($choiceKey)); } catch (e) { return null; }
        },

        remember(value) {
            try { localStorage.setItem(@js($choiceKey), value); } catch (e) {}
        },

        accept() {
            this.remember('granted');
            gtag('consent', 'update', { analytics_storage: 'granted' });
        },

        // Withdrawing consent also removes the _ga cookies already set.
        decline() {
            this.remember('denied');
            gtag('consent', 'update', { analytics_storage: 'denied' });
            document.cookie.split(';').forEach((cookie) => {
                const name = cookie.split('=')[0].trim();
                if (! name.startsWith('_ga')) return;
                ['', '; domain=' + location.hostname, '; domain=.' + location.hostname].forEach((domain) => {
                    document.cookie = name + '=; Max-Age=0; path=/' + domain;
                });
            });
        },
    };

    gtag('consent', 'default', {
        analytics_storage: epesiAnalytics.choice() === 'granted' ? 'granted' : 'denied',
        ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied',
    });
    gtag('js', new Date());
    @if ($account)
        gtag('set', 'user_properties', { demo_account: @js($account) });
    @endif
    gtag('config', @js($id));
    const script = document.createElement('script');
    script.async = true;
    script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(@js($id));
    document.head.appendChild(script);
</script>
