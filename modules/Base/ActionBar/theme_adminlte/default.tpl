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
		'view'      => 'bi-eye',
		'add'       => 'bi-plus-lg',
		'delete'    => 'bi-trash',
		'save'      => 'bi-check2-square',
		'print'     => 'bi-printer',
		'clone'     => 'bi-files',
		'settings'  => 'bi-gear',
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
	}
	$this->assign('icons', $icons);

	// Base_ActionBar::body()/launchpad() hand this theme array_reverse()'d - the
	// default theme floats each item right, which re-reverses the visual order.
	// This theme lays them out with flexbox in source order instead, so reverse
	// back to the intended order here.
	$launcher = array_reverse($this->get_template_vars('launcher'));

	// Quick-access launcher items carry a module-provided icon.png (or a
	// link-specific override, e.g. CRM_Contacts's companies.png/contacts.png)
	// as an already-resolved file path, not a name from the fixed list above -
	// there's no shared enum to map from. Two lookup layers: the icon file's
	// own basename (covers link-level overrides - the only signal that tells
	// sibling links under the same module apart), then the module directory
	// extracted from the resolved path itself (covers a module's own generic
	// icon.png). No inline function declared here - launchpad() re-displays
	// this same template a second time in one request (for the "Launchpad"
	// trigger button), which would fatal on a redeclare.
	$launcher_file_map = array(
		'companies' => 'bi-building',
		'contacts'  => 'bi-person-vcard-fill',
		'launcher'  => 'bi-grid-3x3-gap-fill',
	);
	$launcher_module_map = array(
		'CRM/Calendar'   => 'bi-calendar3',
		'CRM/Contacts'   => 'bi-person-vcard-fill',
		'CRM/Tasks'      => 'bi-list-task',
		'Tests/Bugtrack' => 'bi-bug-fill',
	);
	foreach ($launcher as $k=>$i) {
		$stem = strtolower(pathinfo($i['icon'] ?? '', PATHINFO_FILENAME));
		if (isset($launcher_file_map[$stem])) {
			$launcher[$k]['bi_icon'] = $launcher_file_map[$stem];
		} elseif (preg_match('#modules/([^/]+/[^/]+)/#', $i['icon'] ?? '', $m) && isset($launcher_module_map[$m[1]])) {
			$launcher[$k]['bi_icon'] = $launcher_module_map[$m[1]];
		}
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
