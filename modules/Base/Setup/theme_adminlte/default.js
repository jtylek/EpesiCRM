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
	panel.style.top = (rect.bottom + 4) + 'px';
	panel.style.left = (rect.left + rect.width / 2) + 'px';
	epesi_setup__force_paint(panel);
};

epesi_setup__position_full = function (panelId, card) {
	var panel = document.getElementById(panelId);
	if (!panel || !card) return;
	var rect = card.getBoundingClientRect();
	panel.style.position = 'fixed';
	panel.style.top = (rect.bottom + 4) + 'px';
	panel.style.left = rect.left + 'px';
	panel.style.width = rect.width + 'px';
	epesi_setup__force_paint(panel);
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
	document.getElementById('options_' + name).style.display = 'none';
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
