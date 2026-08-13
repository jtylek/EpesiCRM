{php}
	// $module_icon takes priority (a module-exposed, table/page-specific icon
	// path, e.g. Companies vs Contacts within the one CRM_Contacts module),
	// $module_type is the fallback (the active main module's type string,
	// e.g. "CRM_Contacts") - same two-step lookup and ordering
	// Base_Menu::build_menu_html() uses for the sidebar, via the same shared
	// per-module map, so this can't disagree with the sidebar's icon for the
	// same screen. No icon at all when there's no caption to show (module
	// indicator disabled, or no active module) - matches the default
	// theme's own "hide when empty" behaviour.
	//
	// $module_type is deliberately NOT passed to resolve() whenever
	// $module_icon is present - Utils_RecordBrowser::navigate() (e.g. a row's
	// "View"/"Edit" action) replaces Base_Box's tracked "main module" with a
	// bare Utils_RecordBrowser instance, discarding the real wrapping module
	// (CRM_Contacts, CRM_Tasks, etc) entirely - so $module_type is "browse:
	// CRM_Contacts" but "single-record view: Utils_RecordBrowser" for the
	// exact same table. $module_icon (via icon()/CRM_Contacts::icon()
	// delegation) stays correct either way - it's always the per-table icon
	// file (e.g. "modules/CRM/Contacts/icon.png") - so letting
	// Base_BootstrapIcons::resolve()'s own path auto-extraction read the real
	// module straight out of that path (its 2nd param, $module, must be null
	// for that branch to run at all) sidesteps the wrong-module problem
	// instead of trying to fix Base_Box's own module-tracking. Only fall
	// back to the (possibly-wrong-in-this-one-case, but only source
	// available) $module_type when there's no icon path to extract from.
	require_once('modules/Base/Theme/bootstrap_icons.php');
	$text = $this->get_template_vars('text');
	$module_icon = $this->get_template_vars('module_icon');
	$module_type = $this->get_template_vars('module_type');
	$this->assign('bi_icon', $text ? Base_BootstrapIcons::resolve($module_icon, $module_icon ? null : $module_type, 'bi-app-indicator') : null);
{/php}
{* data-module-type: not used for the icon lookup above (that already
   happened server-side) - exposed here purely as a CSS hook so Base_Box's
   own theme_adminltedark/default.css can target "we're currently showing
   module X" via :has(), e.g. hiding the navbar's Search/Perspective on the
   Base_Admin control panel to leave the (often long, "Administration: ...")
   caption more room. *}
<div class="text epesi-mmi" data-module-type="{$module_type}">
{if $bi_icon}<i class="bi {$bi_icon} epesi-mmi-icon"></i>{/if}{$text}
</div>
