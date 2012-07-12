<div style="text-align:left;padding-left:6px;">
{$form_open}

{foreach from=$form_data.header item=h}
	<div class="epesi_label header" style="width:700px;">
		{$h}
	</div>
{/foreach}
<table style="border-spacing: 3px; width:500px; table-layout:auto;">
	<tr>
		{foreach from=$form_data item=f}
		{if is_array($f) && isset($f.type) && $f.type!='hidden' && $f.type!='button' && $f.type!='submit'}
			<td class="epesi_label" style="width: 10px;white-space: nowrap;">
				{$f.label}{if $f.required}*{/if}
			</td>
			<td class="epesi_data"{if $f.type=='checkbox'} style="width: 10px;"{/if}>
				<div style="position: relative;">
					{$f.error}
					{$f.html}
				</div>
			</td>
		{/if}
		{/foreach}
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
</div>