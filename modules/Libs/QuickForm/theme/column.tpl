{$form_open}

		{php}
			//print_r($this->_tpl_vars['form_data']);
		{/php}
<table style="border-spacing: 3px;">
	{foreach from=$form_data item=f}
		{if is_array($f) && isset($f.type) && $f.type!='hidden' && $f.type!='button' && $f.type!='submit'}
		<tr>
			<td class="epesi_label" style="white-space: nowrap;">
				{$f.label}
			</td>
			<td class="epesi_data" style="min-width: 200px;">
				<div style="position: relative;">
					{$f.error}
					{$f.html}
				</div>
			</td>
		</tr>
		{/if}
	{/foreach}
	<tr>
		<td colspan="2">
			<center class="child_button">
			{foreach from=$form_data item=f}
				{if is_array($f) && isset($f.type) && ($f.type=='button' || $f.type=='submit')}
					{$f.html}
				{/if}
			{/foreach}
			</center>
		</td>
	</tr>
</table>


{$form_close}