{$form_open}
{* Plain Bootstrap .card - see Libs/QuickForm/theme_adminltedark/row.tpl's
   own comment: no epesi-qf-specific override needed, this theme's base
   .card rule already themes itself for light/dark. Per request, every
   QuickForm-rendered form gets this treatment. *}
<div class="card mb-3">
<div class="card-body">

{foreach from=$form_data.header item=h}
	<div class="epesi_label header epesi-qf-header">
		{$h}
	</div>
{/foreach}
<div class="epesi-qf-grid">
	{foreach from=$form_data item=f}
		{if is_array($f) && isset($f.type) && isset($f.html) && isset($f.label) && $f.type!='hidden' && $f.type!='button' && $f.type!='submit'}
		{if $f.label}
		<div class="epesi_label epesi-qf-grid-label">
			{$f.label}{if $f.required}*{/if}
		</div>
		{/if}
		<div class="epesi_data{if $f.frozen} static_field{/if} epesi-qf-grid-data{if !$f.label} epesi-qf-grid-data--full{/if}">
			<div class="epesi-qf-field-wrap">
				{$f.error}
				{$f.html}
			</div>
		</div>
		{/if}
	{/foreach}
</div>
<div class="epesi-qf-buttons">
	{foreach from=$form_data item=f}
		{if is_array($f) && isset($f.type) && ($f.type=='button' || $f.type=='submit')}
			{$f.html}
		{/if}
	{/foreach}
</div>

</div>
</div>
{$form_close}
