{{--
    The message body in a sandboxed iframe: no scripts, no forms, whatever the
    sender put in it. Links open in a new tab, except the compose link
    MailResource::messageBody() rewrites mailto: to, which navigates the
    current window instead (target="_top", requiring
    allow-top-navigation-by-user-activation) — there's no tab strip to open a
    second tab into once Epesi is installed as a desktop app, and every other
    e-mail link in the app already navigates in place.

    The frame is as tall as the message: it never scrolls internally, only
    the page around it does. allow-same-origin is what lets the page measure
    it; without allow-scripts it gives the message nothing, since nothing in
    it can run. Its height is taken with the frame at 0px, since a document
    is never shorter than its window (and many e-mails set html/body to
    height:100%), again once its images have loaded, and again when its
    width changes and the text rewraps. scrolling="no" plus overflow:hidden
    on html/body are a hard backstop for whatever gap remains between a
    resize and the next fit() — e.g. a font swap, or an image that grows the
    layout between two ResizeObserver callbacks the browser coalesces into
    one — so the message clips rather than ever showing its own scrollbar
    next to the page's.
--}}
@php
    $html = '<!doctype html><html><head><meta charset="utf-8"><base target="_blank">'
        .'<style>html,body{overflow:hidden}body{margin:12px;font:14px/1.5 system-ui,sans-serif;color:#111;background:#fff;word-wrap:break-word}img{max-width:100%;height:auto}</style>'
        .'</head><body>'.$getState().'</body></html>';
@endphp
<iframe
    sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation"
    referrerpolicy="no-referrer"
    scrolling="no"
    srcdoc="{{ $html }}"
    title="{{ __('Message body') }}"
    x-data
    x-init="
        const fit = () => {
            const doc = $el.contentDocument;
            if (doc?.location.href !== 'about:srcdoc') return;
            $el.style.height = '0px';
            {{-- At 0px, offsetHeight is the border alone, which the height includes. --}}
            $el.style.height = (doc.documentElement.scrollHeight + $el.offsetHeight) + 'px';
        };
        let width = 0;
        new ResizeObserver(() => {
            if ($el.offsetWidth === width) return;
            width = $el.offsetWidth;
            fit();
        }).observe($el);
        {{-- Late-loading images (the common case) grow the document after
             'load' already fired and after the frame was sized — without
             this, the frame keeps its old height and shows its own
             scrollbar alongside the page's. --}}
        $el.addEventListener('load', () => {
            fit();
            const doc = $el.contentDocument;
            if (doc?.location.href !== 'about:srcdoc') return;
            new ResizeObserver(fit).observe(doc.documentElement);
        });
        fit();
    "
    style="display: block; width: 100%; height: 6rem; border: 1px solid rgba(127,127,127,.25); border-radius: .75rem; background: #fff;"
></iframe>
