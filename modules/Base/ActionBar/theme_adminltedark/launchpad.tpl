{php}
	// Same shared Base_BootstrapIcons map as default.tpl's launcher group and
	// Base_Menu::build_menu_html()'s sidebar icons - see the comment in
	// default.tpl for why an unmatched module now falls back to
	// Base_BootstrapIcons's own generic default instead of its raster image.
	require_once('modules/Base/Theme/bootstrap_icons.php');
	$icons = $this->get_template_vars('icons');
	foreach ($icons as $k=>$i) {
		$icons[$k]['bi_icon'] = Base_BootstrapIcons::resolve($i['icon'] ?? null);
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
