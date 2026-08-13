<?php
/**
 * Bootstrap Icons resolution - theme-agnostic by design (renamed 2026-08-14
 * from Base_AdminlteIcons/adminlte_icons.php, alongside the per-module
 * method's own adminlte_icon() -> bootstrap_icon() rename), even though the
 * adminlte theme is the only consumer wired up today.
 *
 * The sidebar menu (Base_Menu::build_menu_html()) and the ActionBar's
 * quick-access launcher/launchpad icons both draw from this one class, so a
 * given module's icon reads the same wherever it appears instead of being
 * defined ad hoc in each place that happens to render one - previously
 * near-duplicated between Menu_0.php and the ActionBar's two templates. Any
 * future non-adminlte theme wanting the same module-declared icons would
 * call this same resolve() rather than growing its own copy.
 *
 * A module's own icon is no longer kept in a central map here - each module
 * declares it itself, as a `public static function bootstrap_icon()` on its
 * own `<Module>Common` class (e.g. `CRM_Contacts_AccountManagerCommon::
 * bootstrap_icon()` returns 'bi-person-badge'), the same "module opts in by
 * defining a conventionally-named method" shape as `menu()`/`user_settings()`
 * /`home_page()` elsewhere in this codebase - resolve() below just looks it
 * up on demand instead of aggregating every module's up front. Undeclared is
 * the expected case (most modules have no reason to appear in the sidebar/
 * launcher/admin panels), handled by falling back to a plain gear.
 *
 * Deliberately plain PHP (require_once'd, not a Module subclass) - matches
 * why modules/Base/Theme/resolver.php isn't one either: consumers pull this
 * in from a Smarty {php} block or a private method mid-render, not through
 * the module framework's own init_module() path.
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_BootstrapIcons {

	// Keyed by the icon file's own basename (no extension, lowercased) - the
	// only signal that distinguishes sibling links registered by the same
	// module under a link-level __icon__/__icon_small__ override, e.g.
	// CRM_Contacts's own companies.png vs contacts.png. This one stays a
	// central map (not per-module) since it's about telling apart two links
	// from the *same* module, not naming the module itself.
	private static $by_filename = array(
		'companies' => 'bi-building',
		'contacts'  => 'bi-person-vcard-fill',
		'launcher'  => 'bi-grid-3x3-gap-fill',
		// Not a module-disambiguation entry like the three above - this is
		// Base_ActionBar's own generic icons/back.png, reused as a plain
		// "Cancel"/"Back" glyph by callers outside the action bar itself
		// (e.g. Utils_LeightboxPrompt option buttons, CRM_Mail's "Paste
		// e-mail" Cancel). Same bi-arrow-left the action bar's own
		// theme_adminltedark/default.tpl uses for its 'back' action, so the
		// glyph reads the same wherever a "back.png" shows up.
		'back'      => 'bi-arrow-left',
	);

	/**
	 * @param string|null $icon a bare icon filename ("companies.png"), or a
	 *        full resolved path (e.g. "modules/CRM/Calendar/theme/icon.png") -
	 *        only the basename is used for the filename lookup, and if
	 *        $module isn't given, the module segment is extracted from a
	 *        path-shaped value for the module lookup too
	 * @param string|null $module module name, "Vendor_Module" or
	 *        "Vendor/Module" form - takes precedence over anything inferred
	 *        from $icon
	 * @param string|null $fallback returned when the module hasn't declared
	 *        an bootstrap_icon(); null means "let the caller keep the
	 *        original icon/image instead"
	 * @return string|null a "bi-..." class name, or $fallback
	 */
	public static function resolve($icon, $module = null, $fallback = 'bi-gear') {
		if ($icon) {
			$stem = strtolower(pathinfo($icon, PATHINFO_FILENAME));
			if (isset(self::$by_filename[$stem]))
				return self::$by_filename[$stem];
			// A "-small"/"_small" variant (Base_Menu prefers a link's
			// __icon_small__ over __icon__ when both exist; Base_ActionBar's
			// launcher only ever looks at __icon__) means the same artwork at
			// a smaller size, e.g. CRM_Contacts's companies-small.png next to
			// companies.png - without this, the two consumers of this map
			// picked different icons for the very same menu entry.
			$base_stem = preg_replace('/[-_]small$/', '', $stem);
			if ($base_stem !== $stem && isset(self::$by_filename[$base_stem]))
				return self::$by_filename[$base_stem];
		}
		// The module path's own depth varies (most are "modules/Vendor/Module/
		// theme.../...", but a sub-module nests further, e.g. "modules/Premium/
		// Projects/Tickets/theme/icon.png") - grabbing a fixed first two
		// segments silently truncated the deeper case to "Premium/Projects",
		// resolving the WRONG module's bootstrap_icon() (Projects' instead of
		// Tickets') for any caller that didn't already have the real module
		// name in hand. "everything before the last /theme.../." is a
		// depth-independent way to find the true module path.
		if (!$module && $icon && preg_match('#^modules/(.+)/theme[^/]*/#', $icon, $m))
			$module = $m[1];
		if ($module) {
			$class = str_replace('/', '_', $module).'Common';
			if (is_callable(array($class, 'bootstrap_icon'))) {
				$declared = call_user_func(array($class, 'bootstrap_icon'));
				if ($declared) return $declared;
			}
		}
		return $fallback;
	}
}
