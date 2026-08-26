{php}
	// bi_icon resolution mirrors default.tpl's own list_admin_modules() block
	// (Base_Admin's tools listing) - admin()'s $buttons[section][name] array
	// is keyed by the real module name itself (unlike list_admin_modules(),
	// which stashes a separate 'module' key), so $key from the foreach below
	// already IS the module name, no extra plumbing needed. 'bi-gear'
	// fallback kept consistent with that same listing - this panel is still
	// specifically "admin tools", just the per-module access-editing form
	// instead of the launcher grid.
	require_once('modules/Base/Theme/bootstrap_icons.php');
	$sections = $this->get_template_vars('sections');
	foreach ($sections as $sk=>$s) {
		foreach ($s['buttons'] as $key=>$button) {
			$sections[$sk]['buttons'][$key]['bi_icon'] = Base_BootstrapIcons::resolve($button['icon'] ?? null, $key, 'bi-gear');
		}
	}
	$this->assign('sections', $sections);
{/php}
{$form_open}
<div class="epesi-admin-access" id="Base_Admin__access">
{foreach from=$sections key=sk item=s}
	<div class="card epesi-admin-access-card mb-3">
		<div class="card-header">
			<h6 class="mb-0">{$s.header}</h6>
		</div>
		<div class="list-group list-group-flush">
			{foreach key=key item=button from=$s.buttons}
				<div class="list-group-item epesi-admin-access-row" id="{$button.id}">
					<div class="d-flex align-items-center gap-3 flex-wrap">
						<i class="bi {$button.bi_icon} fs-4 text-secondary flex-shrink-0"></i>
						<div class="fw-semibold flex-grow-1 epesi-admin-access-label">{$button.label}</div>
						<div class="epesi-admin-access-switch d-flex align-items-center gap-2">
							{assign var=button_switch value=$button.enable_switch}
							<span class="text-secondary small">{$form_data.$button_switch.label}</span>
							{$form_data.$button_switch.html}
						</div>
					</div>
					<div class="epesi-admin-access-subsections{if !empty($button.sections)} has-content{/if}" id="{$button.sections_id}">
						{if !empty($button.sections)}
						<div class="epesi-admin-access-subgrid">
							{foreach key=section_key item=section from=$button.sections}
								<div class="epesi-admin-access-sublabel text-secondary">{$form_data.$section.label}</div>
								<div class="epesi-admin-access-subcontrol">{$form_data.$section.html}</div>
							{/foreach}
						</div>
						{/if}
					</div>
				</div>
			{/foreach}
		</div>
	</div>
{/foreach}
</div>
{$form_close}
