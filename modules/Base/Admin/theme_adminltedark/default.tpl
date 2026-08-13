{php}
	// Admin_0.php::list_admin_modules() pushes buttons with array_append (not
	// keyed by module name) but now carries the real module name explicitly
	// as $button['module'] - relying on Base_BootstrapIcons::resolve() to
	// extract it from $button['icon'] instead used to mis-detect the module
	// whenever a module had no theme/icon.png of its own: the icon path then
	// fell back to Base_Admin's own icon.png, which resolve() read as "this
	// button belongs to Base_Admin" and rendered Base_AdminCommon's icon
	// (bi-gear-fill) for every such module regardless of its own declared
	// bootstrap_icon() (e.g. Base_AclCommon's bi-person-gear never showed).
	// 'bi-gear' fallback for anything still unplaced - unlike the launcher
	// icons, this panel is specifically "admin tools", so a generic tool
	// glyph fits better than keeping an unmapped module's own image.
	require_once('modules/Base/Theme/bootstrap_icons.php');
	$sections = $this->get_template_vars('sections');
	foreach ($sections as $sk=>$s) {
		foreach ($s['buttons'] as $key=>$button) {
			$sections[$sk]['buttons'][$key]['bi_icon'] = Base_BootstrapIcons::resolve($button['icon'] ?? null, $button['module'] ?? null, 'bi-gear');
		}
	}
	$this->assign('sections', $sections);
{/php}
{* Admin_0.php::list_admin_modules() builds $sections[header]['buttons'][key]
   = array('link'=>'<a href=...>Label</a>', 'icon'=>path) - Base_Theme's
   assign() recursively parses every anchor string it's given into open/text/
   close (Base_ThemeCommon::parse_links()), including nested arrays, so
   $__link mirrors $sections' own shape down to each button's link. *}
<div class="epesi-admin" id="Base_Admin">
{foreach from=$sections key=sk item=s}
	<div class="epesi-admin-section">
		<h6 class="epesi-admin-section-title">{$s.header}</h6>
		<div class="row g-3">
			{foreach key=key item=button from=$s.buttons}
				<div class="col-sm-6 col-lg-4">
					{$__link.sections.$sk.buttons.$key.link.open}
						<div class="card-body d-flex align-items-center gap-3">
							<i class="bi {$button.bi_icon} fs-2 text-secondary"></i>
							<span class="fw-semibold text-body">{$__link.sections.$sk.buttons.$key.link.text}</span>
						</div>
					{$__link.sections.$sk.buttons.$key.link.close}
				</div>
			{/foreach}
		</div>
	</div>
{/foreach}
</div>
