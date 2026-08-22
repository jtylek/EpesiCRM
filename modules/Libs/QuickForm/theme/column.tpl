{$form_open}

{foreach from=$form_data.header item=h}
	<div class="epesi_label header epesi-qf-header">
		{$h}
	</div>
{/foreach}
<div class="epesi-qf-grid">
	{foreach from=$form_data item=f}
		{if is_array($f) && isset($f.type) && isset($f.html) && isset($f.label) && $f.type!='hidden' && $f.type!='button' && $f.type!='submit'}
		<div class="epesi_label epesi-qf-grid-label">
			{$f.label}{if $f.required}*{/if}
		</div>
		<div class="epesi_data{if $f.frozen} static_field{/if} epesi-qf-grid-data">
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

{$form_close}