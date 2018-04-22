<br>
{$form_open}
<table class="Utils_LeightboxPrompt__form" style="border-spacing:3px;width:70%;" cellpadding="0">
	<tr>
		<th style="width:30%;">
		</th>
		<th style="width:70%;">
		</th>
	</tr>
	{foreach item=e from=$form_data}
		{if isset($e.label) && !is_string($e) && $e.type!='hidden' && $e.name!='submit' && $e.name!='cancel'}
			<tr>
			    <td class="epesi_label" style="width:30%;" nowrap="1">
					{$e.label}
				</td>
				<td class="epesi_data{if $e.type=='static' || $e.frozen==1} static_field{/if}{if $e.type=='group'} timestamp{/if}" style="width:70%;">
					<div style="position:relative;" id="{$e.name}__leightbox_prompt__{$id}__data_span">
						{$e.error}
						{$e.html}
					</div>
				</td>
			</tr>
		{/if}
	{/foreach}
	<tr  class="Utils_LeightboxPrompt__form_button">
	    <td style="float:right;">
	    	{$form_data.cancel.html}
		</td>
		<td style="padding-left:5px;">
			{$form_data.submit.html}
		</td>
	</tr>
</table>
{$form_close}