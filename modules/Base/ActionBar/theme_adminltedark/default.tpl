{php}
	// Base_ActionBarCommon::$available_icons is a fixed sprite-position lookup
	// for the default theme's icons.png; this maps the same names onto Bootstrap
	// Icons instead. Kept here rather than in the shared PHP module so the
	// mapping stays theme-scoped - an icon name missing from it (a module using
	// a name outside Base_ActionBarCommon::$available_icons) falls back to a
	// generic glyph rather than rendering blank.
	$icon_map = array(
		'home'      => 'bi-house-door',
		'back'      => 'bi-arrow-left',
		'report'    => 'bi-file-earmark-bar-graph',
		'history'   => 'bi-clock-history',
		'all'       => 'bi-collection',
		'favorites' => 'bi-star-fill',
		'calendar'  => 'bi-calendar3',
		'search'    => 'bi-search',
		'folder'    => 'bi-folder2-open',
		'edit'      => 'bi-pencil-square',
		'company'   => 'bi-building',
		'view'      => 'bi-eye',
		'add'       => 'bi-plus-lg',
		'delete'    => 'bi-trash',
		'save'      => 'bi-check2-square',
		// RecordBrowser_0.php's "Export" (CSV download) button - own key now,
		// not sharing 'save' any more, since the two rendered identically
		// under this theme and read as confusing (an export that looks like
		// a save). bi-filetype-csv per request - names the actual file
		// format this button produces (csv_export.php) rather than a more
		// generic download/arrow-out glyph.
		'export'    => 'bi-filetype-csv',
		'print'     => 'bi-printer',
		'clone'     => 'bi-files',
		'settings'  => 'bi-gear',
		'login-as'  => 'bi-person-circle',
		'scan'      => 'bi-upc-scan',
		'filter'    => 'bi-funnel',
		'retry'     => 'bi-arrow-repeat',
		'send'      => 'bi-send',
		'new-mail'  => 'bi-envelope-plus',
		'attach'    => 'bi-paperclip',
		'reply'     => 'bi-reply-fill',
		'forward'   => 'bi-arrow-90deg-right',
	);
	$icons = $this->get_template_vars('icons');
	foreach ($icons as $k=>$i) {
		if (empty($i['icon_url']))
			$icons[$k]['bi_icon'] = $icon_map[$i['icon']] ?? 'bi-app-indicator';
		// ActionBar_0.php's body() only ever sets 'icon_url' for icons using a
		// file-path icon, leaving it entirely absent (not just falsy) for the
		// named/mapped ones - {if $i.icon_url} below then dereferences a
		// missing array key directly (Smarty compiles dot-notation straight to
		// $i['icon_url'], no isset check), which is silent under normal
		// error_reporting but fatal (exit()) under REPORT_ALL_ERRORS - see
		// [[report-all-errors-exits-on-warning]]. Guarantee the key exists
		// instead of touching the template's {if} syntax.
		$icons[$k]['icon_url'] ??= null;
	}
	$this->assign('icons', $icons);

	// Base_ActionBar::body() now hands this theme its own already-alphabetical
	// (by trimmed label) order directly - no reversal needed here, this
	// theme lays items out with flexbox in source order. (The default theme
	// floats each item right instead, needing the opposite source order -
	// see its own default.tpl for that reversal, not here.)
	$launcher = $this->get_template_vars('launcher');

	// Quick-access launcher items carry a module-provided icon.png (or a
	// link-specific override, e.g. CRM_Contacts's companies.png/contacts.png)
	// as an already-resolved file path, not a name from the fixed list above.
	// Base_AdminlteIcons is the single shared map for this (also used by
	// Base_Menu::build_menu_html()'s sidebar icons, so a module's icon reads
	// the same in both places); a null fallback here means an unmatched
	// module keeps its own original image rather than a generic glyph -
	// unlike the named icons above, these can be genuinely meaningful custom
	// artwork worth keeping.
	require_once('modules/Base/Theme/adminlte_icons.php');
	foreach ($launcher as $k=>$i) {
		$launcher[$k]['bi_icon'] = Base_AdminlteIcons::resolve($i['icon'] ?? null, null, null);
	}
	$this->assign('launcher', $launcher);
{/php}
<div id="Base_ActionBar" class="epesi-actionbar">
	<div class="epesi-actionbar-group">
		{foreach item=i from=$icons}
		{$i.open}
			<span class="epesi-actionbar-btn" helpID="{$i.helpID}">
				{if $i.icon_url}
					<img src="{$i.icon_url}" alt="">
				{else}
					<i class="bi {$i.bi_icon}"></i>
				{/if}
				<span class="epesi-actionbar-label">{$i.label}</span>
			</span>
		{$i.close}
		{/foreach}
	</div>
	{if $launcher}
	<div class="epesi-actionbar-group epesi-actionbar-launcher-group">
		{foreach item=i from=$launcher}
		{$i.open}
			<span class="epesi-actionbar-btn">
				{if $i.bi_icon}
					<i class="bi {$i.bi_icon}"></i>
				{else}
					<img src="{$i.icon}" alt="">
				{/if}
				<span class="epesi-actionbar-label">{$i.label}</span>
			</span>
		{$i.close}
		{/foreach}
	</div>
	{/if}
</div>
