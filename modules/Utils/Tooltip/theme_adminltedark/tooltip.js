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

function epesi_tooltip_position(popup, el) {
	var rect = el.getBoundingClientRect();
	// Above the icon by default, so the popup isn't sitting directly under
	// the mouse pointer (which obscures it) - only falls back to below when
	// there isn't room above (element near the top of the viewport).
	var top = rect.top - 4 - popup.offsetHeight;
	if (top < 4) top = rect.bottom + 4;
	popup.style.top = Math.max(4, top) + 'px';
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

// ajax_open_tag_attrs() - content isn't known until the first hover.
// data-tooltip-html marks callbacks that opted into
// Utils_TooltipCommon::ajax_open_tag_attrs()'s $safe_html param (e.g.
// Watchdog's changes-list tooltip) - req.php then sends already-safe HTML
// (to_safe_html($content,true), see that method's $keep_table doc) instead
// of plain text, and it needs innerHTML here to actually render as a table
// rather than literal "<table>..." text.
function epesi_tooltip_ajax_load(el, tooltipId) {
	try {
		var asHtml = el.hasAttribute('data-tooltip-html');
		epesi_tooltip_show_popup(el, el.getAttribute('data-tooltip-ajax') || '', asHtml);

		if (el.getAttribute('data-epesi-tooltip-loaded') == '1') return;
		el.setAttribute('data-epesi-tooltip-loaded', '1');
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
					el.setAttribute('data-tooltip-ajax', text);
					if (epesi_tooltip_current_el === el) {
						if (text) {
							if (!epesi_tooltip_popup_el) epesi_tooltip_show_popup(el, text, asHtml);
							else {
								if (asHtml) epesi_tooltip_popup_el.innerHTML = text;
								else epesi_tooltip_popup_el.textContent = text;
								epesi_tooltip_position(epesi_tooltip_popup_el, el);
							}
						} else {
							// Nothing to show (e.g. record has no tooltip fields) -
							// don't leave the "Loading..." placeholder stuck.
							epesi_tooltip_hide_popup();
						}
					}
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
