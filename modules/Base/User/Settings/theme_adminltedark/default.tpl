{php}
	// Settings_0.php::main_page() pushes buttons with [] = (numeric index),
	// but - unlike Base_Admin's listing - keeps 'module'=>$module_names
	// (the owning module(s)) right on each button, so Base_BootstrapIcons can
	// resolve from the module name directly rather than reverse-parsing the
	// icon path. 'bi-sliders' fallback for anything unmapped - distinct from
	// the admin tools panel's 'bi-gear', since this is specifically the user
	// settings picker.
	require_once('modules/Base/Theme/bootstrap_icons.php');
	$buttons = $this->get_template_vars('buttons');
	foreach ($buttons as $key=>$button) {
		$modules = $button['module'] ?? null;
		$module = is_array($modules) ? ($modules[0] ?? null) : $modules;
		$buttons[$key]['bi_icon'] = Base_BootstrapIcons::resolve($button['icon'] ?? null, $module, 'bi-sliders');
	}
	$this->assign('buttons', $buttons);
{/php}
{* Settings_0.php::main_page() builds $buttons[] = array('link'=>'<a href=...>
   Label</a>', 'module'=>[...], 'icon'=>path) - Base_Theme's assign()
   recursively parses every anchor string into open/text/close
   (Base_ThemeCommon::parse_links()), so $__link.buttons mirrors $buttons'
   own shape down to each button's link. *}
<div class="epesi-user-settings" id="Base_User_Settings">
	<h6 class="epesi-user-settings-title">{$header}</h6>
	<div class="row g-3">
		{foreach key=key item=button from=$buttons}
			<div class="col-sm-6 col-lg-4">
				{$__link.buttons.$key.link.open}
					<div class="card-body d-flex align-items-center gap-3">
						<i class="bi {$button.bi_icon} fs-2 text-secondary"></i>
						<span class="fw-semibold text-body">{$__link.buttons.$key.link.text}</span>
					</div>
				{$__link.buttons.$key.link.close}
			</div>
		{/foreach}
	</div>
</div>
