<br>
{$form_open}
<div class="Utils_LeightboxPrompt__form" role="table" style="display: grid; grid-template-columns: 30% 70%; width:70%;">
	{foreach item=e from=$form_data}
		{if isset($e.label) && !is_string($e) && $e.type!='hidden' && $e.name!='submit' && $e.name!='cancel'}
			<div class="epesi_label" role="cell" style="white-space: nowrap;">
				{$e.label}
			</div>
			<div class="epesi_data{if $e.type=='static' || $e.frozen==1} static_field{/if}{if $e.type=='group'} timestamp{/if}" role="cell">
				<div style="position:relative;" id="{$e.name}__leightbox_prompt__{$id}__data_span">
					{$e.error}
					{$e.html}
				</div>
			</div>
		{/if}
	{/foreach}
	<div class="Utils_LeightboxPrompt__form_button" role="cell" style="text-align:right;">
		{$form_data.cancel.html}
	</div>
	<div class="Utils_LeightboxPrompt__form_button" role="cell" style="padding-left:5px;">
		{$form_data.submit.html}
	</div>
</div>
{$form_close}