{php}
	// Same Base_BootstrapIcons resolution as Utils_LeightboxPrompt's own chooser
	// grid (Utils/LeightboxPrompt/theme_adminltedark/leightbox.tpl) - $cd['icon']
	// here is already a resolved path (Base_ThemeCommon::get_template_file(),
	// see RecordBrowserCommon_0.php's create_new_record_href()), so resolve()
	// can infer the owning module straight from it. Null fallback keeps each
	// entry's own raster icon (e.g. Premium_Warehouse_Items' inv_item.png/
	// serialized.png/non-inv.png/service.png) when that module hasn't declared
	// a bootstrap_icon().
	require_once('modules/Base/Theme/bootstrap_icons.php');
	$custom_defaults = $this->get_template_vars('custom_defaults');
	foreach ($custom_defaults as $k=>$cd) {
		$custom_defaults[$k]['bi_icon'] = Base_BootstrapIcons::resolve($cd['icon'] ?? null, null, null);
	}
	$this->assign('custom_defaults', $custom_defaults);
{/php}
{* Rendered inside a Leightbox overlay (Libs_LeightboxCommon::display(),
   already AdminLTE-skinned on its own - Libs/Leightbox/theme_adminltedark) by
   Utils_RecordBrowserCommon::create_new_record_href() whenever a module
   registers more than one "new record" type via RecordBrowser::set_defaults()
   (e.g. Premium_Warehouse_Items' Inv./Serialized/Non-Inv./Service picker,
   CRM_Contacts' Company/Contact picker). {$cd.open}/{$cd.close} are raw
   '<a ...>'/'</a>' strings built in RecordBrowserCommon_0.php, landing
   directly as .epesi-nrb-grid's children. Mirrors Utils_LeightboxPrompt's own
   chooser grid almost exactly - see that theme's leightbox.tpl/.css for the
   shared pattern this was modeled on. *}
<div class="epesi-nrb-grid">
	{foreach item=cd from=$custom_defaults}
		{$cd.open}
			{if $cd.bi_icon}
				<i class="bi {$cd.bi_icon}"></i>
			{elseif $cd.icon}
				<img src="{$cd.icon}" alt="">
			{/if}
			<span class="epesi-nrb-label">{$cd.label}</span>
		{$cd.close}
	{/foreach}
</div>
