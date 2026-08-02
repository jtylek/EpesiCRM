{* AdminLTE re-skin of the inline-form variant (an option added via
   add_option()/add_options() with its own 'elements') - the legacy version
   was a bare two-column <table>. Field HTML ({$e.html}) itself comes from
   Libs_QuickForm, unchanged here - this only restructures the label/field
   layout and the Cancel/Submit row around it. *}
{$form_open}
<div class="epesi-lbp-form">
	{foreach item=e from=$form_data}
		{if isset($e.label) && !is_string($e) && $e.type!='hidden' && $e.name!='submit' && $e.name!='cancel'}
			<div class="epesi-lbp-form-row">
				<div class="epesi-lbp-form-label">{$e.label}</div>
				<div class="epesi-lbp-form-data" id="{$e.name}__leightbox_prompt__{$id}__data_span">
					{$e.error}
					{$e.html}
				</div>
			</div>
		{/if}
	{/foreach}
	<div class="epesi-lbp-form-actions">
		{$form_data.cancel.html}
		{$form_data.submit.html}
	</div>
</div>
{$form_close}
