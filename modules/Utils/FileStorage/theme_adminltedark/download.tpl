{* AdminLTE re-skin for Utils_FileStorage's file-info popup
   (Utils_FileStorage_FileLeightbox::get_file_leightbox()). No fixed leightbox
   id suffix here (unlike CRM_Followup's popup) - the id is 'get_file_'.md5(...)
   .md5(microtime()), so this theme's download.css keys off the stable
   "get_file_" prefix instead. Fresh epesi-filedl-* classes rather than
   reusing .epesi_big_button/.epesi_label/.epesi_data, same reasoning as
   CRM_Followup/theme_adminltedark/leightbox.tpl (see AI-shared/adminlte-theme.md
   trap #2). $__link.view/.download/.history/.link are PHP-built <a onclick=...>
   open/text/close pieces (FileLeightbox.php + Base_ThemeCommon::parse_links) -
   fixed by PHP; the icon markup around them is this template's own, so it can
   use real Bootstrap icons instead of the legacy theme's small PNGs.
   $custom_getters entries (e.g. CRM_Roundcube's "Mail" getter) can supply a
   Bootstrap icon class via 'bi' (FileLeightbox.php passes it through from
   file_field_getters()); falls back to the PNG in 'icon' for getters that
   don't set one. *}
<div class="epesi-filedl-info">
	<div class="epesi-filedl-row">
		<div class="epesi-filedl-label">{$labels.filename}</div>
		<div class="epesi-filedl-value">{$filename}</div>
	</div>
	<div class="epesi-filedl-row">
		<div class="epesi-filedl-label">{$labels.file_size}</div>
		<div class="epesi-filedl-value">{$file_size}</div>
	</div>
</div>

<div id="{$download_options_id}" class="epesi-filedl-actions">
	{$__link.view.open}
		<span class="epesi-filedl-btn">
			<i class="bi bi-eye"></i>
			<span class="epesi-filedl-btn-label">{$__link.view.text}</span>
		</span>
	{$__link.view.close}

	{$__link.download.open}
		<span class="epesi-filedl-btn">
			<i class="bi bi-download"></i>
			<span class="epesi-filedl-btn-label">{$__link.download.text}</span>
		</span>
	{$__link.download.close}

	{if $__link.history}
	{$__link.history.open}
		<span class="epesi-filedl-btn">
			<i class="bi bi-clock-history"></i>
			<span class="epesi-filedl-btn-label">{$__link.history.text}</span>
		</span>
	{$__link.history.close}
	{/if}

	{if isset($link)}
	{$__link.link.open}
		<span class="epesi-filedl-btn">
			<i class="bi bi-link-45deg"></i>
			<span class="epesi-filedl-btn-label">{$__link.link.text}</span>
		</span>
	{$__link.link.close}
	{/if}

	{foreach item=p from=$custom_getters}
	{$p.open}
		<span class="epesi-filedl-btn">
			{if $p.bi}
				<i class="bi {$p.bi}"></i>
			{else}
				<img src="{$p.icon}" alt="" class="epesi-filedl-btn-icon">
			{/if}
			<span class="epesi-filedl-btn-label">{$p.text}</span>
		</span>
	{$p.close}
	{/foreach}
</div>
