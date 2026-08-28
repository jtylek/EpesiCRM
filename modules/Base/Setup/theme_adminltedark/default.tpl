{php}
	eval_js('var base_setup__last_filter;');
	eval_js('base_setup__preprocess_filter = base_setup__last_filter;');
	eval_js('base_setup__last_filter = "";');
	load_js('modules/Base/Setup/theme/default.js');
	load_js('modules/Base/Setup/theme_adminltedark/default.js');
	// Default tab on a fresh load (no in-session filter choice to restore yet)
	// is "Updates" when something needs updating - surfaces it immediately
	// instead of leaving it one click away behind the "Updates available"
	// banner below - otherwise "Installed" (not "All"), matching this
	// screen's most common use.
	$default_filter = $this->get_template_vars('updates_count') ? 'updates' : 'installed';
	eval_js('base_setup__filter_by(base_setup__preprocess_filter!=null?base_setup__preprocess_filter:"'.$default_filter.'");');
{/php}

{* Reskin - markup/ids match the default theme's default.tpl (so
   theme/default.js's base_setup__filter_by() and its
   base_setup__last_options/last_actions/last_actions_option globals still
   work), but show/hide of the actions/options panels themselves calls
   theme_adminltedark/default.js's epesi_setup__show/hide_actions/options
   instead of the shared, Prototype-Effect-animated
   base_setup__show/hide_actions/options - see that file's comment for why.
   - #Base_Setup__filter_{arg} ids + the filter's onclick, and the fact that
     JS does a hard className="" / "selected" overwrite (not classList
     add/remove) - styling below keys off ".filters > div"/".selected" only,
     no other class can ride along on those elements.
   - #Base_Setup (the card grid) - base_setup__filter_by() iterates its
     direct childNodes and reads each one's {$f}="1" filter attribute(s) -
     cards must stay direct children, and each keeps position:relative as
     the containing block for its own options/actions panels' CSS fallback
     (before JS repositions them - see default.js).
   - #show_actions_{name}/#hide_actions_{name} (+ __{option} variants) and
     #show_options_{name}/#hide_options_{name}/#options_{name} ids.
   {$package.style}/{$button.style} (install/uninstall/available/store/
   disabled/problem/partial-install) are the semantic status hooks Setup_0.php
   assigns - kept as trailing class tokens, restyled below. config.png/
   config-up.png swapped for inline Bootstrap Icons chevrons. *}
<div class="epesi-setup">
	{if $updates_count}
	<div class="alert alert-warning d-flex align-items-start gap-2 mb-3" role="alert">
		<i class="bi bi-arrow-up-circle-fill mt-1"></i>
		<div>{$updates_alert}</div>
	</div>
	{/if}
	<div class="filters">
		{foreach key=label item=attr from=$filters}
			<div id="Base_Setup__filter_{$attr.arg}" {if !$attr.arg}class="selected"{/if} {if isset($attr.attrs)}{$attr.attrs}{/if} onclick="base_setup__filter_by('{$attr.arg}');">{$label}</div>
		{/foreach}
	</div>

	<div id="Base_Setup" class="epesi-setup-grid">
		{foreach key=name item=package from=$packages}
			<div class="epesi-setup-card" style="position:relative;"{foreach item=f from=$package.filter} {$f}="1"{/foreach}>
				{if $package.url}
					<a href="{$package.url}" target="_blank" class="epesi-setup-card-link">
				{/if}
					{if $package.icon}
						<img class="epesi-setup-card-icon" src="{$package.icon}">
					{/if}
					<div class="epesi-setup-card-name">
						{$package.name}
					</div>
				{if $package.url}
					</a>
				{/if}
				{if $package.version}
					<div class="epesi-setup-card-version">
						{$version_label}{$package.version}
					</div>
				{/if}
				{if $package.readme_id}
					<a class="lbOn btn btn-sm btn-primary rounded-pill px-3 epesi-setup-card-readme" rel="{$package.readme_id}" href="javascript:void(0)">{$labels.readme}</a>
				{/if}
				<div class="epesi-setup-actions">
					<div id="show_actions_{$name}" {$package.buttons_tooltip} class="epesi-setup-action {$package.style}" onclick="epesi_setup__toggle_actions('{$name}',this);">
						<span>{$package.status}</span>{if !empty($package.buttons)}<i class="bi bi-chevron-down"></i>{/if}
					</div>
				{if !empty($package.buttons)}
					<div class="epesi-setup-action-panel" id="hide_actions_{$name}" style="display:none;">
						{foreach from=$package.buttons item=button}
							<div {$button.href} class="epesi-setup-subaction {$button.style}">
								{$button.label}
							</div>
						{/foreach}
					</div>
				{/if}
				{if !empty($package.options)}
					<div id="show_options_{$name}" class="epesi-setup-toggle-options" onclick="epesi_setup__show_options('{$name}');epesi_setup__position_full('options_{$name}',this.closest('.epesi-setup-card'));">
						{$labels.options}<i class="bi bi-chevron-down"></i>
					</div>
					<div id="hide_options_{$name}" class="epesi-setup-toggle-options" onclick="epesi_setup__hide_options('{$name}');" style="display:none;">
						{$labels.options}<i class="bi bi-chevron-up"></i>
					</div>
				{/if}
				</div>
				{if !empty($package.options)}
					<div class="epesi-setup-options" id="options_{$name}" style="display:none;">
						{foreach from=$package.options key=option item=action}
							<div class="epesi-setup-option">
								<div class="epesi-setup-option-label">
									{$action.name}
								</div>
								<div class="epesi-setup-option-action">
									<div id="show_actions_button_{$name}__{$option}" class="epesi-setup-action {$action.style}" onclick="epesi_setup__position_centered('hide_actions_{$name}__{$option}',this);epesi_setup__show_actions('{$name}','{$option}');">
										<span>{$action.status}</span>{if !empty($action.buttons) || $action.readme_id}<i class="bi bi-chevron-down"></i>{/if}
									</div>
									{if !empty($action.buttons) || $action.readme_id}
										<div id="hide_actions_button_{$name}__{$option}" class="epesi-setup-action {$action.style}" onclick="epesi_setup__hide_actions('{$name}','{$option}');" style="display: none;">
											<span>{$action.status}</span><i class="bi bi-chevron-up"></i>
										</div>
									{/if}
								</div>
								{if !empty($action.buttons) || $action.readme_id}
									<div class="epesi-setup-action-panel" id="hide_actions_{$name}__{$option}" style="display:none;">
										{foreach from=$action.buttons item=button}
											<div {$button.href} class="epesi-setup-subaction {$button.style}" onclick="document.getElementById('hide_actions_{$name}').style.display='none';">
												{$button.label}
											</div>
										{/foreach}
										{if $action.readme_id}
											<a class="lbOn epesi-setup-subaction readme" rel="{$action.readme_id}" href="javascript:void(0)">
												{$labels.readme}
											</a>
										{/if}
									</div>
								{/if}
							</div>
						{/foreach}
					</div>
				{/if}
			</div>
		{/foreach}
	</div>
</div>
