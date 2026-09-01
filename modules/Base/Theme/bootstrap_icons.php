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
 * launcher/admin panels), handled by falling back to a generic "window"
 * glyph (bi-layout-text-window-reverse) unless the caller supplies its own
 * more context-appropriate fallback (e.g. Admin's 'bi-gear', Settings'
 * 'bi-sliders').
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
	 * A module's own icon as a ready-to-print <i> tag, for the "New Meeting"/"New Task"/
	 * "New Note"-style record shortcuts that used to print a raster <img> of the module's
	 * icon-small.png. Those images were already invisible under adminltedark (hidden by
	 * CSS, painted over by a ::before glyph keyed on [src*="..."]) but still downloaded -
	 * see AI-shared/performance-profiling.md, 2026-08-31.
	 *
	 * Returns null when the module declares no bootstrap_icon(), so the caller can keep
	 * printing its original <img>.
	 *
	 * @param string      $module  module name, "Vendor_Module" or "Vendor/Module"
	 * @param string      $classes extra classes for the tag (sizing/state)
	 * @return string|null
	 */
	public static function tag($module, $classes = 'action_button') {
		$bi = self::resolve(null, $module, null);
		if (!$bi) return null;
		return '<i class="bi '.$bi.($classes ? ' '.$classes : '').'"></i>';
	}

	/**
	 * A module's icon for one specific recordset it owns, for the modules that
	 * own several and shouldn't have them all read alike - CRM_Contacts
	 * registers both 'contact' and 'company', and a person glyph is plainly
	 * wrong for the latter.
	 *
	 * Declared as a `public static function bootstrap_recordset_icons()` on the
	 * same <Module>Common class as bootstrap_icon(), returning a recordset-name
	 * => "bi-..." map - the same "module opts in by defining a conventionally-
	 * named method" shape, so a per-recordset icon lives next to the module's
	 * own instead of in a central table here. The module's bootstrap_icon()
	 * remains the answer for every recordset the map doesn't name, so a module
	 * owning a single recordset (the common case) declares nothing extra.
	 *
	 * Distinct from $by_filename above, which disambiguates by *icon filename*
	 * for the callers that only ever hold one (menu links, the ActionBar
	 * launcher). This one is for callers that hold the recordset name itself.
	 *
	 * @param string|null $module    module name, "Vendor_Module" or "Vendor/Module"
	 * @param string|null $recordset recordset ("tab") name, e.g. 'company'
	 * @return string|null a "bi-..." class name, or null when the module
	 *         declares no override for this recordset
	 */
	public static function resolve_recordset($module, $recordset) {
		if (!$module || !$recordset) return null;
		$class = str_replace('/', '_', $module).'Common';
		if (!is_callable(array($class, 'bootstrap_recordset_icons'))) return null;
		$map = call_user_func(array($class, 'bootstrap_recordset_icons'));
		return (is_array($map) && !empty($map[$recordset]))? $map[$recordset]: null;
	}

	/**
	 * A small "what kind of record is this" glyph to print immediately before a
	 * title/subject in a list that mixes several record types - the Activities
	 * tab under a Contact/Company, the Agenda applet, the Watchdog applet. Same
	 * per-module icon the sidebar menu shows, so a row reads as "the thing the
	 * Meetings/Tasks/Phonecalls menu entry points at" without a Type column.
	 *
	 * Returns '' (not null) when the module declares no bootstrap_icon() - these
	 * callers concatenate the result straight into a table cell, and an absent
	 * icon should just leave the title unprefixed rather than needing a guard at
	 * every call site.
	 *
	 * @param string|null $module    module name, "Vendor_Module" or "Vendor/Module"
	 * @param string|null $recordset the recordset this row belongs to, when the
	 *        caller knows it - lets a module that owns several override the icon
	 *        per recordset (see resolve_recordset()). Optional: a caller whose
	 *        rows are one-recordset-per-module can leave it out.
	 * @return string
	 */
	public static function type_tag($module, $recordset = null) {
		if (!$module) return '';
		$bi = self::resolve_recordset($module, $recordset) ?: self::resolve(null, $module, null);
		if (!$bi) return '';
		return '<i class="bi '.$bi.' epesi-type-icon text-muted me-1"></i>';
	}

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
	public static function resolve($icon, $module = null, $fallback = 'bi-layout-text-window-reverse') {
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
