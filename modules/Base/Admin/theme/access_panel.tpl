{$form_open}

<table id="Base_Admin__access" cellspacing="0" cellpadding="0">
	<tr>
		<td colspan="4" class="header">{$header}</td>
	</tr>
	{assign var=x value=0}
	{foreach key=key item=button from=$buttons}
	{assign var=x value=$x+1}
		<td class="big-button_container">
			<div class="css3_content_shadow_users" id="{$button.id}">
			<div class="big-button">
				<table>
					<tr>
						<td class="bb_icon">
							{if isset($button.icon)}
							<img src="{$button.icon}" border="0" width="32" height="32" align="middle">
							{/if}
						</td>
						<td rowspan="2" class="bb_form">
							{assign var=button_switch value=$button.enable_switch}
							{$form_data.$button_switch.label}
							{$form_data.$button_switch.html}
						</td>
					</tr>
					<tr>
						<td class="bb_text">
							{$button.label}
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<div id="{$button.sections_id}">
								<table>
									{if !empty($button.sections)}
										{foreach key=section_key item=section from=$button.sections}
											<tr>
												<td style="text-align:right;">
													{$form_data.$section.label}
												</td>
												<td>
													{$form_data.$section.html}
												</td>
											</tr>
										{/foreach}
									{/if}
								</table>
							</div>
						</td>
					</tr>
				</table>
			</div>
 		</div>

		</td>

	<!-- $key holds name of the module -->
	{if ($x%4)==0}
	</tr>
	<tr>
	{/if}
	{/foreach}
	</tr>
</table>

{$form_close}