{{-- Shown while `php artisan demo:reset` puts the demo back (maintenance mode). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">
    <title>{{ __('The demo is being reset') }}</title>
</head>
<body style="font-family:system-ui,sans-serif;max-width:36rem;margin:5rem auto;padding:0 1rem;line-height:1.5;color:#111">
    <h1 style="font-size:1.5rem">{{ __('The demo is being reset') }}</h1>
    <p>{{ __('Its data goes back to the start once a day. This takes about a minute; the page reloads by itself.') }}</p>
</body>
</html>
