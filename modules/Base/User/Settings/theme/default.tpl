<table id="Base_User_Settings" cellspacing="0" cellpadding="0">
	<tr>
		<td colspan="4" class="header">{$header}</td>
	</tr>
	{assign var=x value=0}
	{foreach key=key item=button from=$buttons}
	{assign var=x value=$x+1}
		<td>
			<div class="css3_content_shadow_users">
			{$__link.buttons.$key.link.open}
			<div class="big-button">
				<div class="bb_icon">
					{if isset($button.icon)}
					<img src="{$button.icon}" border="0" width="32" height="32" align="middle">
					{/if}
				</div>
					<table border="0" cellpadding="0" cellspacing="0">
						<tbody>
							<tr>
								<td class="bb_text">
									{$__link.buttons.$key.link.text}
								</td>
							</tr>
						</tbody>
					</table>
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
