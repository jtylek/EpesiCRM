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
	require_once('modules/Base/Theme/adminlte_icons.php');
	$text = $this->get_template_vars('text');
	$module_icon = $this->get_template_vars('module_icon');
	$module_type = $this->get_template_vars('module_type');
	$this->assign('bi_icon', $text ? Base_AdminlteIcons::resolve($module_icon, $module_type, 'bi-app-indicator') : null);
{/php}
<div class="text epesi-mmi">
{if $bi_icon}<i class="bi {$bi_icon} epesi-mmi-icon"></i>{/if}{$text}
</div>
