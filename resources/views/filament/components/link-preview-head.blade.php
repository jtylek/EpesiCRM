{{--
    The link preview WhatsApp, Teams or Slack show for a pasted link. Their
    crawler isn't signed in, so it sees the login page: the login title from
    Administration → Logo and title (the portal's own on the portal) and the
    app icon, the same one the installed app and the home screen show.
    First thing in <head> (AppServiceProvider): a crawler reads only the start
    of the page, and further down, after Filament's styles, it didn't get them.
--}}
@php
    $ogTitle = filament()->getCurrentPanel()?->getId() === 'portal'
        ? \App\Support\Appearance\AppName::portal()
        : \App\Support\Appearance\AppName::login();
@endphp
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $ogTitle }}">
<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:description" content="{{ __('CRM :name', ['name' => $ogTitle]) }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ asset('images/pwa/icon-512.png') }}">
<meta name="twitter:card" content="summary">
