{{--
    The message body in a sandboxed iframe: no scripts, no forms, no access to
    the CRM page, whatever the sender put in it. Links open in a new tab.
--}}
@php
    $html = '<!doctype html><html><head><meta charset="utf-8"><base target="_blank">'
        .'<style>body{margin:12px;font:14px/1.5 system-ui,sans-serif;color:#111;background:#fff;word-wrap:break-word}img{max-width:100%;height:auto}</style>'
        .'</head><body>'.$getRecord()->displayHtml().'</body></html>';
@endphp
<iframe
    sandbox="allow-popups allow-popups-to-escape-sandbox"
    referrerpolicy="no-referrer"
    srcdoc="{{ $html }}"
    title="{{ __('Message body') }}"
    style="width: 100%; min-height: 32rem; height: 60vh; resize: vertical; border: 1px solid rgba(127,127,127,.25); border-radius: .75rem; background: #fff;"
></iframe>
