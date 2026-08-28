/* .app-main (AdminLTE's own scrollable content column) is "overflow:auto",
 * which clips any position:absolute descendant that extends past its
 * visible/scrolled bounds - z-index can't fix a clip, only a stacking
 * order. These panels are switched to position:fixed (escapes ancestor
 * overflow entirely, only ancestor transform/filter/will-change would stop
 * it, and none of #main/#content/#content_body/.epesi-setup-card set any)
 * with their on-screen position computed here from the trigger's/card's own
 * bounding rect.
 *
 * theme/default.js's base_setup__show_options()/show_actions() use
 * Prototype's Effect.BlindDown/Effect.Scale, which actively manipulate the
 * element's own width/position/overflow as part of their scale-reveal
 * animation - directly fighting our position:fixed + explicit width/left
 * (observed: corrupted width, phantom page-wide horizontal scrollbar).
 * Rather than patch around a 15-year-old animation library's internals,
 * these theme-scoped replacements skip the animation entirely (plain
 * instant show/hide - normal for a dropdown) while still reading/writing
 * the exact same base_setup__last_options/last_actions/last_actions_option
 * globals theme/default.js's own base_setup__filter_by() depends on, so
 * switching filters while a panel is open still closes it correctly.
 * theme/default.js itself is left untouched - shared with the (unaffected,
 * non-scroll-clipped) default theme.
 */

function epesi_setup__force_paint(el) {
	void el.offsetHeight;
}

epesi_setup__position_centered = function (panelId, trigger) {
	var panel = document.getElementById(panelId);
	if (!panel) return;
	var rect = trigger.getBoundingClientRect();
	panel.style.position = 'fixed';
	/* Panel size varies with its button labels (Install/Uninstall/Readme...)
	 * and is still display:none at this point (position runs before show,
	 * both here and in epesi_setup__toggle_actions) - briefly reveal it,
	 * synchronously and before any repaint, just to measure its real
	 * rendered size instead of guessing off CSS's min-width. */
	var wasHidden = panel.style.display === 'none';
	if (wasHidden) panel.style.display = '';
	var halfWidth = panel.offsetWidth / 2;
	var panelHeight = panel.offsetHeight;
	if (wasHidden) panel.style.display = 'none';
	var margin = 8;
	/* Centering purely on the trigger, below it (previous behavior), pushes
	 * the panel past the viewport's right edge for any card near the end of
	 * a row, and past the bottom edge for any trigger near the bottom of the
	 * window (e.g. the last row of a long "Optional" list, which is exactly
	 * where this fires from) - it's fixed-positioned so nothing clips or
	 * scrolls it back into view in either direction. Clamp the center point
	 * horizontally (the panel's own edges - center +/- halfWidth, since
	 * .epesi-setup-action-panel's CSS still applies transform:translateX
	 * (-50%) to this left value - must stay within the window) and flip the
	 * panel above the trigger instead of below when there isn't enough room
	 * underneath. */
	var centerX = rect.left + rect.width / 2;
	var minCenter = halfWidth + margin;
	var maxCenter = Math.max(minCenter, window.innerWidth - halfWidth - margin);
	panel.style.left = Math.min(Math.max(centerX, minCenter), maxCenter) + 'px';
	var top = rect.bottom + 4;
	if (top + panelHeight + margin > window.innerHeight) {
		var flippedTop = rect.top - 4 - panelHeight;
		top = flippedTop >= margin ? flippedTop : Math.max(margin, window.innerHeight - panelHeight - margin);
	}
	panel.style.top = top + 'px';
	epesi_setup__force_paint(panel);
};

epesi_setup__position_full = function (panelId, card) {
	var panel = document.getElementById(panelId);
	if (!panel || !card) return;
	/* .container-fluid (an ancestor between the card and <body>) is
	 * overflow-y:hidden, which clips a position:absolute panel anchored to
	 * the card the moment it runs past that ancestor's own bottom edge -
	 * position:fixed used to be the workaround, but a fixed panel sits
	 * outside the page's scrollable flow, so a long options list (e.g. 9+
	 * entries) had nowhere to go but an internal scrollbar. Reparenting to
	 * <body> (which, like <html>, is overflow:visible - confirmed the whole
	 * document/window is the real scroll container here) escapes the clip
	 * *and* lets the panel's full height become part of the page's own
	 * scrollable content, so a long list just makes the window taller
	 * instead of needing its own nested scrollbar. */
	if (!panel._epesiHomeParent) {
		panel._epesiHomeParent = panel.parentNode;
		panel._epesiHomeNext = panel.nextSibling;
	}
	document.body.appendChild(panel);
	var rect = card.getBoundingClientRect();
	panel.style.position = 'absolute';
	panel.style.top = (rect.bottom + window.scrollY + 4) + 'px';
	panel.style.left = (rect.left + window.scrollX) + 'px';
	panel.style.width = rect.width + 'px';
	panel.style.maxHeight = '';
	panel.style.overflowY = '';
	epesi_setup__force_paint(panel);
};

epesi_setup__restore_home = function (panel) {
	if (panel && panel._epesiHomeParent) {
		panel._epesiHomeParent.insertBefore(panel, panel._epesiHomeNext);
	}
};

epesi_setup__show_options = function (name) {
	if (base_setup__last_options && base_setup__last_options != name) {
		epesi_setup__hide_options(base_setup__last_options);
	}
	document.getElementById('show_options_' + name).style.display = 'none';
	document.getElementById('hide_options_' + name).style.display = '';
	document.getElementById('options_' + name).style.display = '';
	base_setup__last_options = name;
};

epesi_setup__hide_options = function (name) {
	document.getElementById('show_options_' + name).style.display = '';
	document.getElementById('hide_options_' + name).style.display = 'none';
	var panel = document.getElementById('options_' + name);
	panel.style.display = 'none';
	/* Undo position_full()'s reparent to <body> - otherwise a later
	 * re-render of #Base_Setup (e.g. after switching filters) would leave
	 * this panel behind as an orphaned duplicate at the end of <body>. */
	epesi_setup__restore_home(panel);
	base_setup__last_options = false;
};

epesi_setup__show_actions = function (name, option) {
	if ((base_setup__last_actions && base_setup__last_actions != name)
			|| (base_setup__last_actions_option && base_setup__last_actions_option != option)) {
		epesi_setup__hide_actions(base_setup__last_actions, base_setup__last_actions_option);
	}
	var el_id = name;
	if (option) {
		el_id = el_id + '__' + option;
		document.getElementById('show_actions_button_' + name + '__' + option).style.display = 'none';
		document.getElementById('hide_actions_button_' + name + '__' + option).style.display = '';
	}
	var panel = document.getElementById('hide_actions_' + el_id);
	if (panel) panel.style.display = '';
	base_setup__last_actions = name;
	base_setup__last_actions_option = option;
};

epesi_setup__hide_actions = function (name, option) {
	if (!name) return;
	var el_id = name;
	if (option) {
		el_id = el_id + '__' + option;
		document.getElementById('show_actions_button_' + name + '__' + option).style.display = '';
		document.getElementById('hide_actions_button_' + name + '__' + option).style.display = 'none';
	}
	var panel = document.getElementById('hide_actions_' + el_id);
	if (panel) panel.style.display = 'none';
	base_setup__last_actions = false;
	base_setup__last_actions_option = false;
};

epesi_setup__toggle_actions = function (name, trigger) {
	var chevron = trigger ? trigger.querySelector('.bi-chevron-down, .bi-chevron-up') : null;
	if (base_setup__last_actions == name && !base_setup__last_actions_option) {
		epesi_setup__hide_actions(name);
		if (chevron) chevron.classList.replace('bi-chevron-up', 'bi-chevron-down');
	} else {
		epesi_setup__position_centered('hide_actions_' + name, trigger);
		epesi_setup__show_actions(name);
		if (chevron) chevron.classList.replace('bi-chevron-down', 'bi-chevron-up');
	}
};
