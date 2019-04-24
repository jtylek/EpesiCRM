<table id="Base_User_Settings" cellspacing="0" cellpadding="0">
	<tr>
		<td colspan="4" class="epesi_label header">{$header}</td>
	</tr>
	{assign var=x value=0}
	{foreach key=key item=button from=$buttons}
	{assign var=x value=$x+1}
		<td>
			<div>
			{$__link.buttons.$key.link.open}
			<div class="epesi_big_button bigger">
				{if isset($button.icon)}
					<img src="{$button.icon}" border="0" width="32" height="32" align="middle">
				{/if}
				<span>
					{$__link.buttons.$key.link.text}
				</span>
			</div>
			{$__link.buttons.$key.link.close}
			</div>
		</td>
		<!-- $key holds name of the module -->
	{if ($x%4)==0}
	<tr>
	{/if}
	{/foreach}
	</tr>
</table>
