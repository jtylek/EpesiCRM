<?php

namespace Epesi\Modules\Mail\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Strips an archived message's own styling before it is shown, so every
 * e-mail reads the same way regardless of what the sender's HTML did — a
 * marketing e-mail that sets its own colours can otherwise make its text
 * unreadable (e.g. light text on a colour we don't honour, or vice versa)
 * instead of leaving that to App\Support's body.blade.php, which colours
 * the message from the app's own light/dark theme.
 *
 * A dedicated Symfony HtmlSanitizer config, not Filament's shared one
 * (Str::sanitizeHtml(), bound in SupportServiceProvider): that one keeps
 * `style` and `class` on purpose, for the rich-text editor. Here neither is
 * allowed on any element, and only true formatting/structural tags are kept
 * — no `table`, `div` or `span`, which marketing e-mails use purely for
 * layout.
 *
 * The config's own default for an element nobody allowed is already Drop
 * — the whole subtree, text included — and that default is kept: a
 * commercial e-mail like PayPal's puts its <style> directly in the body
 * (not under <head>), and the sanitizer silently ignores any config for a
 * head-only element (style, script, title, …) once it's outside <head>,
 * falling through to the default action instead — so Drop is what keeps a
 * stray <style>'s raw CSS text from being kept as if it were readable
 * content. What default Drop gets wrong is <table>/<td>/<div> — pure
 * layout, also dropped whole by default, which collapsed nearly the whole
 * PayPal message rather than just its styling. Each is listed with
 * blockElement() instead: unwrapped, but its children (and their text)
 * stay, so a layout table collapses into running text instead of vanishing.
 */
class PlainBody
{
    public static function sanitize(string $html): string
    {
        $html = static::sanitizer()->sanitize($html);

        // Blocking a layout table/div (see below) still serializes its
        // original indentation as whitespace text nodes — harmless in
        // rendered HTML, which collapses it anyway, but a commercial e-mail
        // like PayPal's nests deeply enough that this turned 25KB of real
        // content into a data-body attribute with hundreds of blank lines,
        // which broke Livewire/Alpine's client-side processing of the page
        // (an "Invalid or unexpected token" error, and an empty body).
        // Collapsing it to single spaces keeps the same visible text.
        return trim((string) preg_replace('/\s+/', ' ', $html));
    }

    protected static function sanitizer(): HtmlSanitizer
    {
        static $sanitizer = null;

        return $sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                // Layout-only tags: unwrapped, children kept, so they read
                // as running text rather than vanishing under the default
                // Drop (see the class docblock).
                ->blockElement('table')
                ->blockElement('thead')
                ->blockElement('tbody')
                ->blockElement('tfoot')
                ->blockElement('tr')
                ->blockElement('td')
                ->blockElement('th')
                ->blockElement('caption')
                ->blockElement('colgroup')
                ->blockElement('col')
                ->blockElement('div')
                ->blockElement('span')
                ->blockElement('font')
                ->blockElement('center')
                ->blockElement('section')
                ->blockElement('article')
                ->blockElement('header')
                ->blockElement('footer')
                ->blockElement('nav')
                ->blockElement('aside')
                ->blockElement('figure')
                ->blockElement('figcaption')
                ->allowElement('p')
                ->allowElement('br')
                ->allowElement('b')
                ->allowElement('strong')
                ->allowElement('i')
                ->allowElement('em')
                ->allowElement('u')
                ->allowElement('s')
                ->allowElement('blockquote')
                ->allowElement('ul')
                ->allowElement('ol')
                ->allowElement('li')
                ->allowElement('h1')
                ->allowElement('h2')
                ->allowElement('h3')
                ->allowElement('h4')
                ->allowElement('h5')
                ->allowElement('h6')
                ->allowElement('a', ['href', 'target'])
                ->allowElement('img', ['src', 'alt'])
                ->allowLinkSchemes(['http', 'https', 'mailto'])
                ->allowMediaSchemes(['http', 'https'])
                ->withMaxInputLength(500000),
        );
    }
}
