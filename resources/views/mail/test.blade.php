<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#F7F6F2;font-family:'Titillium Web',Segoe UI,Arial,sans-serif;color:#0F1923">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F6F2;padding:28px 12px">
  <tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px">
      <tr><td style="padding:0 0 18px"><img src="{{ $message->embed(base_path('bootstrap/preflight-logo-2x.png')) }}" alt="Epesi" width="128" height="28" style="display:block"></td></tr>
      <tr><td style="background:#ffffff;border:1px solid #E2E8EE;border-radius:14px;padding:30px 30px 26px;font-size:16px;line-height:1.6">
        <h1 style="font-family:Exo,'Titillium Web',Arial,sans-serif;font-size:22px;color:#1A2B4A;margin:0 0 14px">{{ __('E-mail configuration test') }}</h1>
        <p style="margin:0">{!! __('If you are reading this, it means that your e-mail server configuration at :url is working properly.', ['url' => '<a href="'.e($url).'" style="color:#1F9D5A">'.e($url).'</a>']) !!}</p>
      </td></tr>
      <tr><td style="padding:18px 6px;color:#64748B;font-size:13px;line-height:1.5;text-align:center">
        {{ __('Sent from Administration → Mail server settings.') }}
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
