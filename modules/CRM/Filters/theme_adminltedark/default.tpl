{* Content of the "Perspective" leightbox popup (Filters_0.php::body(), rendered
   into Libs_LeightboxCommon::display('crm_filters', ...)). Previously had no
   adminltedark override, so it fell through to the default theme's own
   default.tpl - raw <img> icons and un-styled <a> links inside the (adminlte-
   skinned) leightbox chrome, which read as broken next to the rest of the
   AdminLTE UI.

   $__link.my / $__link.manage are whole '<a ...>text</a>' strings assigned by
   Filters_0.php - Base_ThemeCommon::parse_links() (invoked from Base_Theme's
   assign(), see Theme_0.php::display()) splits those into open/text/close
   automatically, same mechanism Base/User/Settings's adminltedark template
   relies on. $filters[]'s 'open'/'close' are plain tag fragments assigned
   directly by Filters_0.php (not a full anchor string), so they're used as-is. *}
<div class="epesi-filters-popup">
	<div class="epesi-filters-actions">
		{$__link.my.open}
			<span class="epesi-filters-btn">
				<i class="bi bi-person-fill"></i>
				<span class="epesi-filters-label">{$__link.my.text}</span>
			</span>
		{$__link.my.close}

		{if isset($all)}
			{$__link.all.open}
				<span class="epesi-filters-btn">
					<i class="bi bi-collection"></i>
					<span class="epesi-filters-label">{$__link.all.text}</span>
				</span>
			{$__link.all.close}
		{/if}

		{$__link.manage.open}
			<span class="epesi-filters-btn">
				<i class="bi bi-gear-fill"></i>
				<span class="epesi-filters-label">{$__link.manage.text}</span>
			</span>
		{$__link.manage.close}
	</div>

	<div class="epesi-filters-form">
		{$contacts_open}
			<span class="epesi-filters-form-label">{$contacts_data.crm_filter_contact.label}</span>
			<span class="epesi-filters-autoselect">{$contacts_data.crm_filter_contact.html}</span>
			<span class="epesi-filters-submit">{$contacts_data.submit.html}</span>
		{$contacts_close}
	</div>

	{if !empty($filters)}
		<div class="epesi-filters-saved">
			<div class="epesi-filters-saved-header">{$saved_filters}</div>
			<div class="epesi-filters-actions">
				{foreach item=p key=k from=$filters}
					{$p.open}
						<span class="epesi-filters-btn">
							<i class="bi bi-bookmark-fill"></i>
							<span class="epesi-filters-label">{$p.title}</span>
						</span>
					{$p.close}
				{/foreach}
			</div>
		</div>
	{/if}
</div>
