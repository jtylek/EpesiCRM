{{-- Shown from the moment the form is sent. It stays through the redirect, the slow part of a first sign-in, and goes when the answer is anything else (wrong password, too many attempts). --}}
<div id="epesi-sign-in-progress" role="status" hidden>
    <div class="epesi-sign-in-spinner" aria-hidden="true"></div>
    <div>{{ __('Preparing Epesi - please wait...') }}</div>
</div>
<style>
    #epesi-sign-in-progress{position:fixed;inset:0;z-index:9999;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:20px;padding:16px;text-align:center;font-size:18px;background:#f4f7fb;color:#142033}
    #epesi-sign-in-progress[hidden]{display:none}
    .dark #epesi-sign-in-progress{background:#111827;color:#f3f4f6}
    .epesi-sign-in-spinner{width:44px;height:44px;border:4px solid #dce4ee;border-top-color:#0b9f5a;border-radius:50%;animation:epesi-sign-in-spin 1s linear infinite}
    .dark .epesi-sign-in-spinner{border-color:#374151;border-top-color:#0b9f5a}
    @keyframes epesi-sign-in-spin{to{transform:rotate(360deg)}}
</style>
<script>
    (function () {
        if (window.epesiSignInProgress) { return; }
        window.epesiSignInProgress = true;
        function overlay() { return document.getElementById('epesi-sign-in-progress'); }
        function hide() { var o = overlay(); if (o) { o.hidden = true; } }
        document.addEventListener('submit', function () { var o = overlay(); if (o) { o.hidden = false; } }, true);
        function watch() {
            Livewire.hook('commit', function (commit) {
                if (!commit.component.el.querySelector('form')) { return; }
                commit.succeed(function (result) { if (!(result.effects && result.effects.redirect)) { hide(); } });
                commit.fail(hide);
            });
        }
        if (window.Livewire) { watch(); } else { document.addEventListener('livewire:init', watch); }
    })();
</script>
