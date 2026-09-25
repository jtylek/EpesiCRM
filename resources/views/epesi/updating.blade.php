{{-- Shown by RedirectToDatabaseUpdate to users other than administrators while a database update is waiting. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('epesi is being updated') }}</title>
</head>
<body style="font-family:system-ui,sans-serif;max-width:36rem;margin:5rem auto;padding:0 1rem;line-height:1.5;color:#111">
    <h1 style="font-size:1.5rem">{{ __('epesi is being updated') }}</h1>
    <p>{{ __('A new version has been installed and is being set up. Please try again in a few minutes. If this doesn\'t go away, tell your administrator.') }}</p>
</body>
</html>
