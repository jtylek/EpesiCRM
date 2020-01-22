<br>
{$form_open}
<table class="Utils_LeightboxPrompt__form">
	<tr>
		<th>
		</th>
		<th>
		</th>
	</tr>
	{foreach item=e from=$form_data}
		{if isset($e.label) && !is_string($e) && $e.type!='hidden' && $e.name!='submit' && $e.name!='cancel'}
			<tr>
			    <td class="epesi_label">
					{$e.label}
				</td>
				<td class="epesi_data{if $e.type=='static' || $e.frozen==1} static_field{/if}{if $e.type=='group'} timestamp{/if}">
					<div style="position:relative;" id="{$e.name}__leightbox_prompt__{$id}__data_span">
						{$e.error}
						{$e.html}
					</div>
				</td>
			</tr>
		{/if}
	{/foreach}
	<tr  class="Filters_header">
	    <td class="Filters_header">{$form_data.cancel.html}</td>
		<td class="Filters_header">{$form_data.submit.html}</td>
	</tr>
</table>
{$form_close}