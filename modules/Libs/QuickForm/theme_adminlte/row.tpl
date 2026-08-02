<div class="epesi-qf-row-wrap">
{$form_open}

{if isset($form_data.header)}
{foreach from=$form_data.header item=h}
	<div class="epesi_label header epesi-qf-header">
		{$h}
	</div>
{/foreach}
{/if}
<div class="epesi-qf-row">
	{foreach from=$form_data item=f}
		{if is_array($f) && isset($f.type) && isset($f.html) && isset($f.label) && $f.type!='hidden' && $f.type!='button' && $f.type!='submit'}
		<div class="epesi-qf-row-item">
			<span class="epesi_label epesi-qf-row-label">
				{$f.label}{if $f.required}*{/if}
			</span>
			<span class="epesi_data{if $f.frozen} static_field{/if} epesi-qf-row-data">
				{$f.error}
				{$f.html}
			</span>
		</div>
		{/if}
	{/foreach}
	{foreach from=$form_data item=f}
		{if is_array($f) && isset($f.type) && ($f.type=='button' || $f.type=='submit')}
		<div class="epesi-qf-row-item">
			<div class="child_button">
				{$f.html}
			</div>
		</div>
		{/if}
	{/foreach}
</div>

{$form_close}
</div>
