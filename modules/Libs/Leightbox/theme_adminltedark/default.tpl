{php}
	load_js('modules/Libs/Leightbox/theme/default.js');
	load_js('modules/Libs/Leightbox/theme_adminltedark/default.js');
{/php}

<div id="Leightbox_header">
	<div class="epesi-leightbox-title">{$header}</div>
	<div class="epesi-leightbox-actions">
		{* Maximize/restore toggle - a plain CSS class swap
		   (.leightbox.maximized, see default.css), deliberately not
		   libs_leightbox_resize()'s hardcoded inline top/left/width/height
		   percentages (shared theme/default.js, loaded above): those don't
		   account for this theme's transform-based centering and visibly
		   jump the popup to the old-style positioning, which is exactly why
		   that button was omitted here in the first place. $resize_label is
		   still assigned by Libs_LeightboxCommon::get() but unused by this
		   theme; $maximize_label/$restore_label back this button instead -
		   epesi_leightbox_toggle_maximize() (theme_adminltedark/default.js,
		   loaded above) toggles the class and swaps this button's own
		   icon/title between them. *}
		<a class="epesi-leightbox-btn epesi-leightbox-maximize" href="javascript:void(0)"
			onclick="epesi_leightbox_toggle_maximize(this); return false;"
			title="{$maximize_label}"
			data-maximize-label="{$maximize_label}"
			data-restore-label="{$restore_label}">
			<i class="bi bi-arrows-fullscreen"></i>
		</a>
		<a class="epesi-leightbox-btn" {$close_href} title="{$close_label}">
			<i class="bi bi-x-lg"></i>
		</a>
	</div>
</div>

<div id="Leightbox_content">
    {$content}
</div>
