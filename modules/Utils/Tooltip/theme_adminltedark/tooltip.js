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

// Whether the pointer gesture in progress right now is a touch tap, checked
// by epesi_tooltip_show()/epesi_tooltip_ajax_load() below to bail out before
// ever opening a popup off one. First attempt at this used
// matchMedia('(hover: none)') instead - a device-capability check, applied
// once up front to strip onmouseenter off every tooltip element on such a
// device - but that kept failing to suppress the popup on a real phone in
// practice (device/emulator hover-capability reporting is inconsistent
// enough not to trust as the sole gate). This tracks the actual gesture
// instead of the device's declared capability, which sidesteps that
// entirely and also does the right thing on hybrid touch+mouse hardware
// (still shows on genuine mouse hover there). Works because of the fixed
// order browsers replay for a tap on an element that didn't call
// preventDefault on its touchstart: touchstart, then a synthetic mouseover/
// mouseenter/mousemove/mousedown/mouseup/click sequence - touchstart always
// lands first, so the flag is already true by the time onmouseenter (and
// therefore epesi_tooltip_show()/epesi_tooltip_ajax_load()) fires for that
// same tap; the synthetic mousemove a step later then clears it again,
// ready for the next real hover. Both PHP-side tooltip functions' own
// `if(MOBILE_DEVICE) return '';` guard (TooltipCommon_0.php) can't help here
// either way - MOBILE_DEVICE has been permanently 0 since
// detect_mobile_device() was deleted with the legacy mobile system (see
// AI-shared/deliberate-removals.md's "Legacy mobile system" entry), so every
// tooltip still renders fully hover-wired even on a phone; this flag is a
// client-side patch of that server-rendered wiring instead. aria-label (set
// alongside onmouseenter by both PHP functions) still carries the tooltip
// text for assistive tech regardless of this flag - screen readers read it
// directly off the element, no JS/hover involved.
var epesi_touch_active = false;
document.addEventListener('touchstart', function() { epesi_touch_active = true; }, true);
document.addEventListener('mousemove', function() { epesi_touch_active = false; }, true);

function epesi_tooltip_position(popup, el) {
	// documentElement.clientWidth/clientHeight, not window.innerWidth/
	// innerHeight: inner* includes the scrollbar's own width, so on any page
	// tall enough to have a vertical scrollbar (routine in this app) the
	// clamp below let the popup's right edge land a scrollbar-width past the
	// actual visible content area - it rendered fine, just partly hidden
	// under the scrollbar track, which read as "tooltip near the right edge
	// doesn't render full width". client* is the visible-content size the
	// scrollbar has already been subtracted from, so clamping against it
	// keeps the whole popup off the scrollbar.
	var viewportWidth = document.documentElement.clientWidth;
	var viewportHeight = document.documentElement.clientHeight;
	var rect = el.getBoundingClientRect();
	var spaceAbove = rect.top - 4;
	var spaceBelow = viewportHeight - rect.bottom - 4;
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
	popup.style.top = Math.max(4, Math.min(top, viewportHeight - popup.offsetHeight - 4)) + 'px';
	popup.style.left = Math.max(4, Math.min(rect.left, viewportWidth - popup.offsetWidth - 4)) + 'px';
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
	if (epesi_touch_active) return;
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
	if (epesi_touch_active) return;
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

// Deferred to window 'load' (not run at top level here) so this doesn't
// depend on this static file's own load order relative to jQuery - e:load
// is a jQuery-only custom event (jQuery(document).trigger(...), never
// bubbled as a real DOM CustomEvent), so jq(document).on(...) is the only
// way to catch it and genuinely needs jq to already exist.
window.addEventListener('load', function() {
	try {
		// A showing popup's only cleanup path is the triggering element's own
		// mouseleave (epesi_tooltip_show_popup() above) - but most tooltipped
		// elements are icon links (Fullscreen, Configure, Toggle, RecordBrowser
		// row actions, ...) whose click swaps the surrounding DOM via Epesi's
		// ajax push (include/epesi.js's Epesi.request()) instead of a real page
		// navigation. That destroys the element mid-hover without ever
		// dispatching mouseleave, so the popup is orphaned in <body> and
		// outlives the content it was pointing at (reported: Shoutbox's
		// Fullscreen tooltip surviving into the fullscreen view; same shape
		// wherever else a tooltipped icon triggers an ajax swap). 'e:loading'
		// fires right before every such swap (see epesi.js), so hiding here
		// mirrors the existing Utils_Calendar.destroy pattern of tearing down
		// transient UI on that event.
		jq(document).on('e:loading', epesi_tooltip_hide_popup);
	} catch (e) {}
});
