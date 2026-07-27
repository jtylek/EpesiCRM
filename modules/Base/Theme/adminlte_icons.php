<?php
/**
 * Central per-module Bootstrap Icons mapping for the adminlte theme.
 *
 * The sidebar menu (Base_Menu::build_menu_html()) and the ActionBar's
 * quick-access launcher/launchpad icons both draw from this one map, so a
 * given module's icon reads the same wherever it appears instead of being
 * defined ad hoc in each place that happens to render one - previously
 * near-duplicated between Menu_0.php and the ActionBar's two templates.
 *
 * Deliberately plain PHP (require_once'd, not a Module subclass) - matches
 * why modules/Base/Theme/resolver.php isn't one either: consumers pull this
 * in from a Smarty {php} block or a private method mid-render, not through
 * the module framework's own init_module() path.
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_AdminlteIcons {

	// Keyed by the icon file's own basename (no extension, lowercased) - the
	// only signal that distinguishes sibling links registered by the same
	// module under a link-level __icon__/__icon_small__ override, e.g.
	// CRM_Contacts's own companies.png vs contacts.png.
	private static $by_filename = array(
		'companies' => 'bi-building',
		'contacts'  => 'bi-person-vcard-fill',
		'launcher'  => 'bi-grid-3x3-gap-fill',
	);

	// Keyed by module name in "Vendor/Module" form (resolve() also accepts
	// "Vendor_Module" and normalises it) - covers a module's own generic
	// icon.png/icon-small.png. Not exhaustive - every module in the app would
	// be a lot to keep current - so an unmatched module is expected and
	// handled by the caller's own $fallback.
	private static $by_module = array(
		'Apps/ActivityReport'                  => 'bi-graph-up',
		'Apps/Shoutbox'                         => 'bi-chat-square-text',
		'Base/About'                            => 'bi-info-circle',
		'Base/Acl'                              => 'bi-shield-lock',
		'Base/Admin'                            => 'bi-gear-fill',
		'Base/Cron'                             => 'bi-clock',
		'Base/Dashboard'                        => 'bi-speedometer2',
		'Base/EpesiStore'                       => 'bi-box-seam',
		'Base/Error'                            => 'bi-bug-fill',
		'Base/EssClient'                        => 'bi-diagram-3',
		'Base/HomePage'                         => 'bi-house-door',
		'Base/Lang/Administrator'               => 'bi-translate',
		'Base/Mail'                             => 'bi-envelope-at',
		'Base/Print'                            => 'bi-printer',
		'Base/Search'                           => 'bi-search',
		'Base/Setup'                            => 'bi-image',
		'Base/Theme/Administrator'              => 'bi-palette',
		'Base/Menu/QuickAccess'                 => 'bi-star',
		'Base/Notify'                           => 'bi-bell-fill',
		'Base/RegionalSettings'                 => 'bi-globe',
		'Base/User'                             => 'bi-people-fill',
		'Base/User/Administrator'               => 'bi-people-fill',
		'Base/User/Settings'                    => 'bi-gear',
		'CRM/Calendar'                          => 'bi-calendar3',
		'CRM/Common'                            => 'bi-gear',
		'CRM/Contacts'                          => 'bi-person-vcard-fill',
		'CRM/Contacts/AccountManager'           => 'bi-person-badge',
		'CRM/Contacts/NotesAggregate'           => 'bi-journal-text',
		'CRM/Fax'                               => 'bi-printer',
		'CRM/Filters'                           => 'bi-funnel',
		'CRM/LoginAudit'                        => 'bi-door-open',
		'CRM/Mail'                              => 'bi-envelope-fill',
		'CRM/Meeting'                           => 'bi-calendar-event',
		'CRM/PhoneCall'                         => 'bi-telephone-fill',
		'CRM/Roundcube'                         => 'bi-envelope-open-fill',
		'CRM/Tasks'                             => 'bi-list-task',
		'Data/Countries'                        => 'bi-flag',
		'Data/TaxRates'                         => 'bi-percent',
		'Libs/QuickForm'                        => 'bi-input-cursor-text',
		'Libs/TCPDF'                            => 'bi-file-earmark-pdf',
		'Tools/SessionKeeper'                   => 'bi-hourglass-split',
		'Tools/WhoIsOnline'                     => 'bi-people',
		'Utils/Attachment'                      => 'bi-paperclip',
		'Utils/CommonData'                      => 'bi-database',
		'Utils/CurrencyField'                   => 'bi-cash-coin',
		'Utils/FileStorage'                     => 'bi-folder2',
		'Utils/GenericBrowser'                  => 'bi-table',
		'Utils/Messenger'                       => 'bi-envelope',
		'Utils/Planner'                         => 'bi-kanban',
		'Utils/PopupCalendar'                   => 'bi-calendar3',
		'Utils/RecordBrowser'                   => 'bi-table',
		'Utils/RecordBrowser/Filters'           => 'bi-funnel',
		'Utils/Tooltip'                         => 'bi-chat-square-dots',
		'Utils/Tray'                            => 'bi-bell',
		'Utils/Watchdog'                        => 'bi-eye',
		'Tests/Attachment'                      => 'bi-paperclip',
		'Tests/Bugtrack'                        => 'bi-bug-fill',
		'Tests/Calendar'                        => 'bi-calendar3',
		'Tests/Callbacks'                       => 'bi-arrow-return-right',
		'Tests/Codepress'                       => 'bi-code-slash',
		'Tests/Colorpicker'                     => 'bi-palette',
		'Tests/Comment'                         => 'bi-chat-left-text',
		'Tests/GenericBrowser'                  => 'bi-table',
		'Tests/Image'                           => 'bi-image',
		'Tests/Lang'                            => 'bi-translate',
		'Tests/Leightbox'                       => 'bi-window',
		'Tests/Menu'                            => 'bi-list',
		'Tests/OpenFlashChart'                  => 'bi-pie-chart',
		'Tests/QuickForm'                       => 'bi-ui-checks',
		'Tests/RecordBrowser'                   => 'bi-table',
		'Tests/Report'                          => 'bi-file-earmark-bar-graph',
		'Tests/SharedUniqueHref'                => 'bi-link-45deg',
		'Tests/TabbedBrowser'                   => 'bi-window-stack',
		'Tests/Tooltip'                         => 'bi-chat-square-dots',
		'Tests/Wizard'                          => 'bi-magic',
		'Utils/CustomMenu'                      => 'bi-list-ul',
		'Utils/RecordBrowser/CustomRecordsets'  => 'bi-table',
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
	 * @param string|null $fallback returned when nothing matches; null means
	 *        "let the caller keep the original icon/image instead"
	 * @return string|null a "bi-..." class name, or $fallback
	 */
	public static function resolve($icon, $module = null, $fallback = 'bi-app-indicator') {
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
		if (!$module && $icon && preg_match('#modules/([^/]+/[^/]+)/#', $icon, $m))
			$module = $m[1];
		if ($module) {
			$key = str_replace('_', '/', $module);
			if (isset(self::$by_module[$key]))
				return self::$by_module[$key];
		}
		return $fallback;
	}
}
