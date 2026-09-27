{{--
    Lets the browser install epesi as an app (Chrome/Edge: "Install epesi";
    phones: "Add to Home Screen"). The manifest (WebAppManifestController)
    asks for its own window, with no address bar or tabs, and keeps that
    across every page, which a page can't do in a normal tab. iOS Safari
    reads the apple-* tags instead of the manifest.

    theme-color is the installed window's title bar: the top bar's own
    colour, white or neutral-900.
--}}
<link rel="manifest" href="{{ route('web-app.manifest') }}">
<link rel="apple-touch-icon" href="{{ asset('images/pwa/apple-touch-icon.png') }}">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#171717" media="(prefers-color-scheme: dark)">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="epesi">
