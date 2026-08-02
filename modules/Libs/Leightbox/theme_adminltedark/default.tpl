{php}
	load_js('modules/Libs/Leightbox/theme/default.js');
{/php}

<div id="Leightbox_header">
	<div class="epesi-leightbox-title">{$header}</div>
	<div class="epesi-leightbox-actions">
		{* No resize button - libs_leightbox_resize()'s hardcoded inline
		   top/left/width/height percentages (shared default.js) don't account
		   for this theme's transform-based centering, so it visibly jumped the
		   popup to the old-style positioning. $resize_label is still assigned
		   by Libs_LeightboxCommon::get() but unused here. *}
		<a class="epesi-leightbox-btn" {$close_href} title="{$close_label}">
			<i class="bi bi-x-lg"></i>
		</a>
	</div>
</div>

<div id="Leightbox_content">
    {$content}
</div>
