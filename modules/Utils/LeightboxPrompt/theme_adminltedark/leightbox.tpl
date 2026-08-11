{php}
	// Same shared Base_AdminlteIcons map every other icon consumer uses (sidebar
	// menu, ActionBar launcher/Launchpad, admin panels) - see the comment in
	// modules/Base/Theme/adminlte_icons.php. A null fallback keeps this
	// button's original <img> (a legacy PNG, e.g. CRM_Calendar's own event-type
	// icons) if the option's own module hasn't declared an adminlte_icon().
	require_once('modules/Base/Theme/adminlte_icons.php');
	$buttons = $this->get_template_vars('buttons');
	foreach ($buttons as $k=>$b) {
		$buttons[$k]['bi_icon'] = Base_AdminlteIcons::resolve($b['icon'] ?? null, null, null);
	}
	$this->assign('buttons', $buttons);
{/php}
{* Rendered inside a Leightbox overlay (Libs_LeightboxCommon::display(), already
   AdminLTE-skinned on its own - modules/Libs/Leightbox/theme_adminltedark).
   This is the generic Utils_LeightboxPrompt chooser grid any module can
   add_option()s into (e.g. CRM_Calendar's "New Event" -> Phonecall/Task/
   Meeting/...), so it can't assume a fixed button count - a fluid flex-wrap
   grid instead of the legacy version's row-of-6-then-wrap <table>.
   {$b.open}/{$b.close} are raw '<a ...>'/'</a>' strings built in
   LeightboxPrompt_0.php::body(), landing directly as .epesi-lbp-grid's
   children. Identical to theme_adminlte's copy on purpose - see
   leightbox.css. *}
{$open_buttons_section}
<div class="epesi-lbp-grid">
	{foreach item=b from=$buttons}
		{$b.open}
			{if $b.bi_icon}
				<i class="bi {$b.bi_icon}"></i>
			{elseif $b.icon}
				<img src="{$b.icon}" alt="">
			{/if}
			<span class="epesi-lbp-label">{$b.label}</span>
		{$b.close}
	{/foreach}
</div>
{$close_buttons_section}

{foreach item=b from=$sections}
	{$b}
{/foreach}
{$additional_info}
