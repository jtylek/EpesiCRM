/* AdminLTE replacement for Utils_Tooltip: plain native browser tooltips
 * (title="...") instead of the default theme's custom mouse-tracking
 * #tooltip_div. Bootstrap's own JS Tooltip component was tried here first
 * and repeatedly conflicted with other hover-driven scripts already present
 * in this app - reverted by request to this, since a bare title attribute
 * has no JS lifecycle of its own and so cannot conflict with anything.
 *
 * This file only has one job left: ajax_open_tag_attrs()'s variant, where
 * the tooltip's real content isn't known at render time. The element is
 * rendered with a "Loading..." placeholder in its title and this
 * onmouseenter handler; on first hover it fetches the real content from
 * the same modules/Utils/Tooltip/req.php the default theme's JS already
 * posts to, and rewrites the title attribute in place. Native tooltips
 * don't refresh their text mid-display, so the very first hover on a given
 * element still shows "Loading..." once - the fetched content shows from
 * the next hover onward.
 */
function epesi_tooltip_ajax_load(el, tooltipId) {
	try {
		if (el.getAttribute('data-epesi-tooltip-loaded') == '1') return;
		el.setAttribute('data-epesi-tooltip-loaded', '1');
		jq.ajax({
			type: 'POST',
			url: 'modules/Utils/Tooltip/req.php',
			data: { tooltip_id: tooltipId, cid: Epesi.client_id },
			success: function(html) {
				try {
					if (!html) return;
					// html may contain markup (format_info_tooltip() etc.) -
					// native tooltips only ever show plain text.
					var div = document.createElement('div');
					div.innerHTML = html;
					var text = (div.textContent || div.innerText || '').replace(/\s+/g, ' ').trim();
					if (text) el.setAttribute('title', text);
				} catch (e) {}
			}
		});
	} catch (e) {}
}
