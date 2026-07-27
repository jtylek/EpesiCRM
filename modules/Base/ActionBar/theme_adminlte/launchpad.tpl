{php}
	// Same module/link-icon-to-Bootstrap-Icons mapping as default.tpl's
	// launcher group - see the comment there for why it's basename/module
	// keyed rather than a fixed enum. Kept in sync manually; there are only
	// two copies (this file and default.tpl) and no function to declare
	// (each renders in a separate Smarty compile unit anyway).
	$icons = $this->get_template_vars('icons');
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
	foreach ($icons as $k=>$i) {
		$stem = strtolower(pathinfo($i['icon'] ?? '', PATHINFO_FILENAME));
		if (isset($launcher_file_map[$stem])) {
			$icons[$k]['bi_icon'] = $launcher_file_map[$stem];
		} elseif (preg_match('#modules/([^/]+/[^/]+)/#', $i['icon'] ?? '', $m) && isset($launcher_module_map[$m[1]])) {
			$icons[$k]['bi_icon'] = $launcher_module_map[$m[1]];
		}
	}
	$this->assign('icons', $icons);
{/php}
{* Rendered inside a Leightbox overlay by Base_ActionBar::launchpad() - a grid
   of the user's pinned quick-access modules. Reuses the same button classes
   as default.tpl so one CSS file covers both. *}
<div class="epesi-actionbar-launchpad">
	{foreach item=i from=$icons}
	{$i.open}
		<span class="epesi-actionbar-btn epesi-actionbar-launchpad-btn">
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
