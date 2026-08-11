{php}
	load_js('modules/Libs/Leightbox/theme/default.js');
{/php}

<div id="Leightbox_header" style="display: flex;">
	{* Was <a onClick="...this.parentNode" x6> reaching up through
	   td/tr/tbody/table/#Leightbox_header to the outer .leightbox wrapper
	   div (see LeightboxCommon_0.php::get()) - now 3 hops instead of 6
	   (a -> .left -> #Leightbox_header -> .leightbox), matching the
	   shallower div structure (see AI-shared/adminlte-theme.md). *}
	<div class="left">
		<a class="launchpad_icon_resize" onClick="libs_leightbox_resize(this.parentNode.parentNode.parentNode)">
		<nobr><span class="launchpad_icon_resize_text">{$resize_label}</span></nobr>
		</a>
	</div>
	<div class="center" style="flex: 1 1 auto;">{$header}</div>
	<div class="right">
		<a class="launchpad_icon_close" {$close_href}>
			<nobr><span class="launchpad_icon_close_text">{$close_label}</span></nobr>
		</a>
	</div>
</div>

<div id="Leightbox_content">
    {$content}
</div>
