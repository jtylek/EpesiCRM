{php}
	// Admin_0.php::list_admin_modules() pushes buttons with array_append (not
	// keyed by module name), so the module identity isn't directly available
	// here - but $button['icon'] is already Base_ThemeCommon::get_template_file()'s
	// resolved "modules/<Vendor>/<Module>/theme/icon.png" path (or a custom
	// admin_icon() path, best-effort), which Base_AdminlteIcons::resolve() can
	// extract the module from itself. 'bi-gear' fallback for anything it can't
	// place - unlike the launcher icons, this panel is specifically "admin
	// tools", so a generic tool glyph fits better than keeping an unmapped
	// module's own image.
	require_once('modules/Base/Theme/adminlte_icons.php');
	$sections = $this->get_template_vars('sections');
	foreach ($sections as $sk=>$s) {
		foreach ($s['buttons'] as $key=>$button) {
			$sections[$sk]['buttons'][$key]['bi_icon'] = Base_AdminlteIcons::resolve($button['icon'] ?? null, null, 'bi-gear');
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
