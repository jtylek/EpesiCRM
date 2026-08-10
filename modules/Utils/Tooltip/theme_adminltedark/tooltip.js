/* AdminLTE replacement for Utils_Tooltip: the default theme's custom
 * mouse-tracking #tooltip_div is unused here (see TooltipCommon_0.php).
 *
 * Both open_tag_attrs() ("help" tooltips, e.g. icon actions) and
 * ajax_open_tag_attrs() (RecordBrowser hover tooltips) show a plain
 * .epesi-tooltip-popup div appended to <body> on hover. A pure CSS
 * ::after-based popup was tried first (no JS at all) but had to be dropped:
 * it's clipped by any ancestor with overflow:hidden, which isn't just
 * RecordBrowser/GenericBrowser table cells (their ellipsis truncation) but
 * also plain Bootstrap .card containers (used for rounded corners on
 * dashboard applets, admin panels, etc.) - i.e. most of the app. Bootstrap's
 * own JS Tooltip *component* was tried even earlier and repeatedly
 * conflicted with other hover-driven scripts already present here - this is
 * deliberately not that: no component, no event delegation, just plain
 * mouseenter/mouseleave pairs, mirroring the existing
 * modules/Utils/GenericBrowser/js/table_overflow.js technique for escaping
 * an overflow:hidden ancestor by appending straight to <body>.
 */
var epesi_tooltip_popup_el = null;
var epesi_tooltip_current_el = null;
// Shared across every element on the page, keyed by tooltip_id
// (ajax_open_tag_attrs()'s md5(serialize($tooltip_settings)), see
// TooltipCommon_0.php) - safe to share because two elements only ever get
// the same tooltip_id when their callback+args+safe_html serialized
// identically, i.e. they'd fetch byte-identical content anyway (e.g. the
// same contact record linked from several rows of a RecordBrowser list).
// epesi_tooltip_ajax_pending dedupes concurrent hovers of same-ID elements
// that happen before the first one's response has come back.
var epesi_tooltip_ajax_cache = {};
var epesi_tooltip_ajax_pending = {};

function epesi_tooltip_position(popup, el) {
	var rect = el.getBoundingClientRect();
	var spaceAbove = rect.top - 4;
	var spaceBelow = window.innerHeight - rect.bottom - 4;
	// Above the icon by default, so the popup isn't sitting directly under
	// the mouse pointer (which obscures it) - but only when above is
	// actually the roomier side. Comparing available space (not just "does
	// it fit above") matters for a tall ajax popup (e.g. Watchdog's
	// changes-list) on an icon near the bottom of the screen: the old "only
	// fall back to below when it doesn't fit above" check would still flip
	// to below there, which has even less room and let the popup run off
	// the bottom of the viewport uncapped. Final clamp keeps it fully inside
	// the viewport either way (best-effort - a popup taller than the whole
	// viewport still clips, just at the edge instead of mid-content).
	var top = (spaceAbove >= popup.offsetHeight || spaceAbove >= spaceBelow)
		? rect.top - 4 - popup.offsetHeight
		: rect.bottom + 4;
	popup.style.top = Math.max(4, Math.min(top, window.innerHeight - popup.offsetHeight - 4)) + 'px';
	popup.style.left = Math.max(4, Math.min(rect.left, window.innerWidth - popup.offsetWidth - 4)) + 'px';
}

function epesi_tooltip_hide_popup() {
	if (epesi_tooltip_popup_el) {
		epesi_tooltip_popup_el.remove();
		epesi_tooltip_popup_el = null;
	}
	epesi_tooltip_current_el = null;
}

// asHtml=true renders via innerHTML: open_tag_attrs()'s content is always a
// small safe subset (<strong>/<b>/<br> only, see TooltipCommon_0.php's
// to_safe_html()) - server-controlled, never re-parsed user HTML. The ajax
// path (epesi_tooltip_ajax_load() below) defaults to asHtml=false/textContent
// instead: its content has normally been through html_entity_decode()
// server-side (to_plain_text()), which would be unsafe to hand to innerHTML -
// except for callbacks that opted into $safe_html (data-tooltip-html), whose
// response skips that decode step and is safe the same way open_tag_attrs()'s
// is.
function epesi_tooltip_show_popup(el, content, asHtml) {
	epesi_tooltip_hide_popup();
	if (!content) return;
	var popup = document.createElement('div');
	popup.className = 'epesi-tooltip-popup';
	if (asHtml) popup.innerHTML = content;
	else popup.textContent = content;
	document.body.appendChild(popup);
	epesi_tooltip_position(popup, el);
	epesi_tooltip_popup_el = popup;
	epesi_tooltip_current_el = el;
	el.addEventListener('mouseleave', epesi_tooltip_hide_popup, { once: true });
}

// open_tag_attrs() - content is already known server-side, no ajax needed.
function epesi_tooltip_show(el) {
	try {
		epesi_tooltip_show_popup(el, el.getAttribute('data-tooltip') || '', true);
	} catch (e) {}
}

// Applies a resolved ajax response (from cache, or just back from req.php)
// to one waiting element: updates its own data-tooltip-ajax (so a later
// leightbox-mode click, or a cache hit next hover, has it too) and, if
// that element is the one currently under the mouse, paints/updates the
// live popup.
function epesi_tooltip_ajax_apply(el, text, asHtml) {
	el.setAttribute('data-tooltip-ajax', text);
	if (epesi_tooltip_current_el !== el) return;
	if (text) {
		if (!epesi_tooltip_popup_el) epesi_tooltip_show_popup(el, text, asHtml);
		else {
			if (asHtml) epesi_tooltip_popup_el.innerHTML = text;
			else epesi_tooltip_popup_el.textContent = text;
			epesi_tooltip_position(epesi_tooltip_popup_el, el);
		}
	} else {
		// Nothing to show (e.g. record has no tooltip fields) - don't leave
		// the "Loading..." placeholder stuck.
		epesi_tooltip_hide_popup();
	}
}

// ajax_open_tag_attrs() - content isn't known until the first hover, unless
// some other element already fetched the same tooltip_id (epesi_tooltip_ajax_cache)
// or is currently fetching it (epesi_tooltip_ajax_pending) - see the cache
// vars' own comment above for why sharing by tooltip_id is safe.
// data-tooltip-html marks callbacks that opted into
// Utils_TooltipCommon::ajax_open_tag_attrs()'s $safe_html param (e.g.
// Watchdog's changes-list tooltip) - req.php then sends already-safe HTML
// (to_safe_html($content,true), see that method's $keep_table doc) instead
// of plain text, and it needs innerHTML here to actually render as a table
// rather than literal "<table>..." text.
function epesi_tooltip_ajax_load(el, tooltipId) {
	try {
		var asHtml = el.hasAttribute('data-tooltip-html');

		if (epesi_tooltip_ajax_cache.hasOwnProperty(tooltipId)) {
			// epesi_tooltip_ajax_apply() only *updates* a popup that's
			// already showing for el (it no-ops unless
			// epesi_tooltip_current_el === el, which is set by
			// epesi_tooltip_show_popup() below) - a cache hit hasn't called
			// that yet, so it must paint the popup itself here, exactly like
			// epesi_tooltip_show()'s sync data-tooltip path does.
			var cached = epesi_tooltip_ajax_cache[tooltipId];
			el.setAttribute('data-tooltip-ajax', cached);
			el.setAttribute('data-epesi-tooltip-loaded', '1');
			epesi_tooltip_show_popup(el, cached, asHtml);
			return;
		}

		epesi_tooltip_show_popup(el, el.getAttribute('data-tooltip-ajax') || '', asHtml);

		if (el.getAttribute('data-epesi-tooltip-loaded') == '1') return;
		el.setAttribute('data-epesi-tooltip-loaded', '1');

		if (epesi_tooltip_ajax_pending[tooltipId]) {
			// Another element with the same tooltip_id is already in flight -
			// piggyback on its response instead of firing a second request.
			epesi_tooltip_ajax_pending[tooltipId].push(el);
			return;
		}
		epesi_tooltip_ajax_pending[tooltipId] = [el];

		jq.ajax({
			type: 'POST',
			url: 'modules/Utils/Tooltip/req.php',
			data: { tooltip_id: tooltipId, cid: Epesi.client_id },
			success: function(text) {
				try {
					// req.php returns plain text (Utils_TooltipCommon::to_plain_text()) by
					// default, one "Label: value" per line, or (asHtml) the to_safe_html()
					// equivalent - either way no further parsing needed here.
					text = (text || '').trim();
					epesi_tooltip_ajax_cache[tooltipId] = text;
					var waiting = epesi_tooltip_ajax_pending[tooltipId] || [];
					delete epesi_tooltip_ajax_pending[tooltipId];
					waiting.forEach(function(waitingEl) {
						epesi_tooltip_ajax_apply(waitingEl, text, waitingEl.hasAttribute('data-tooltip-html'));
					});
				} catch (e) {}
			}
		});
	} catch (e) {}
}

// tooltip_leightbox_mode() (TooltipCommon_0.php) wires this via onmousedown
// on the SAME element open_tag_attrs()/ajax_open_tag_attrs() already put its
// own hover-tooltip data-* attributes on, so a click/tap re-shows that same
// content inside the bigger Leightbox popup (Libs_Leightbox's own lbOn-click
// handling opens the popup shell regardless of theme; only populating
// #tooltip_leightbox_mode_content needs this theme-specific read, since the
// legacy theme's js/tooltip.js reads a differently-named tip="..." attribute
// instead). Content-safety mirrors epesi_tooltip_show_popup()'s asHtml rule
// exactly: data-tooltip (open_tag_attrs(), sync) is always safe-html: skip
// straight to innerHTML. data-tooltip-ajax is only safe for innerHTML when
// data-tooltip-html marks it as the $safe_html/$keep_table ajax variant -
// otherwise (or before the ajax response has arrived, still "Loading...")
// it's plain text, same textContent-vs-innerHTML split as the hover popup.
function epesi_tooltip_leightbox_populate(el) {
	try {
		var target = document.getElementById('tooltip_leightbox_mode_content');
		if (!target) return;
		if (el.hasAttribute('data-tooltip')) {
			target.innerHTML = el.getAttribute('data-tooltip') || '';
		} else if (el.hasAttribute('data-tooltip-ajax')) {
			var text = el.getAttribute('data-tooltip-ajax') || '';
			if (el.hasAttribute('data-tooltip-html')) target.innerHTML = text;
			else target.textContent = text;
		}
	} catch (e) {}
}

// Touch devices have no hover, so open_tag_attrs()/ajax_open_tag_attrs()'s
// onmouseenter never fires from a deliberate, unambiguous gesture the way it
// does with a mouse: a quick tap on the <a> most of these attributes sit on
// just navigates before our popup registers, while a long-press synthesizes
// mouseenter around the same hold duration the browser uses to decide "show
// the native link context menu" (Open in New Tab/Copy Link/...) - so holding
// the link shows both at once, visibly colliding. Neither PHP function's own
// `if(MOBILE_DEVICE) return '';` guard (TooltipCommon_0.php) saves us here -
// MOBILE_DEVICE has been permanently 0 since detect_mobile_device() was
// deleted with the legacy mobile system (see
// AI-shared/deliberate-removals.md's "Legacy mobile system" entry) - so every
// tooltip renders fully hover-wired even on a phone.
//
// Fix: give touch users a dedicated, unambiguous tap target instead of the
// hold gesture. On any (hover:none) device, every [data-epesi-tooltip="1"]
// element that's inside a real link gets a small trailing button appended
// right after that link (not inside it - nesting a <button> inside an <a>
// is invalid HTML and behaves inconsistently), wired to the exact same
// onmouseenter handler the server already rendered (reused via a captured
// reference, not reimplemented). Tapping the button shows the tooltip
// without navigating (preventDefault/stopPropagation); tapping the link
// itself navigates immediately like any other link, since the link's own
// onmouseenter is cleared so the browser's native hold gesture no longer
// triggers our popup at all - only the browser's own context menu does,
// same as any ordinary link.
function epesi_tooltip_mobile_outside_tap_dismiss(e) {
	if (!epesi_tooltip_popup_el || epesi_tooltip_popup_el.contains(e.target)) return;
	epesi_tooltip_hide_popup();
	document.removeEventListener('click', epesi_tooltip_mobile_outside_tap_dismiss, true);
}

function epesi_tooltip_mobile_show(origHandler, el) {
	try {
		origHandler.call(el);
		// Re-registered on every tap rather than once globally, deferred via
		// setTimeout so the very same tap that opened the popup doesn't reach
		// this listener itself - capture phase runs before the button's own
		// bubble-phase stopPropagation takes effect, so an immediate add
		// would close the popup right back on the tap that opened it.
		document.removeEventListener('click', epesi_tooltip_mobile_outside_tap_dismiss, true);
		setTimeout(function() {
			document.addEventListener('click', epesi_tooltip_mobile_outside_tap_dismiss, true);
		}, 0);
	} catch (e) {}
}

function epesi_tooltip_mobile_enhance() {
	try {
		if (!window.matchMedia || !window.matchMedia('(hover: none)').matches) return;
		document.querySelectorAll('[data-epesi-tooltip="1"]:not(.epesi-tooltip-mobile-done)').forEach(function(el) {
			el.classList.add('epesi-tooltip-mobile-done');
			var anchor = el.closest('a[href]');
			if (!anchor) return;
			var origHandler = el.onmouseenter;
			if (typeof origHandler !== 'function') return;
			el.onmouseenter = null;
			var label = el.getAttribute('aria-label');
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'epesi-tooltip-mobile-trigger';
			btn.setAttribute('aria-label', label || 'Show info');
			btn.innerHTML = '<i class="bi bi-info-circle" aria-hidden="true"></i>';
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				epesi_tooltip_mobile_show(origHandler, el);
			});
			anchor.parentNode.insertBefore(btn, anchor.nextSibling);
		});
	} catch (e) {}
}

// Deferred to window 'load' (not run at top level here) so this doesn't
// depend on this static file's own load order relative to jQuery - e:load
// is a jQuery-only custom event (jQuery(document).trigger(...), never
// bubbled as a real DOM CustomEvent), so jq(document).on(...) is the only
// way to catch it and genuinely needs jq to already exist.
window.addEventListener('load', function() {
	try {
		epesi_tooltip_mobile_enhance();
		jq(document).on('e:load', function() {
			try { epesi_tooltip_mobile_enhance(); } catch (e) {}
		});
	} catch (e) {}
});
