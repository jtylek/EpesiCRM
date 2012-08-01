{$form_open}

<div style="max-width:900px" id="Base_Admin__access">
{foreach from=$sections key=sk item=s}
	<div class="epesi_label header" style="clear:both;">{$s.header}</div>
    <div class="buttons_container">
		{foreach key=key item=button from=$s.buttons}
			{$__link.sections.$sk.buttons.$key.link.open}
				<div class="epesi_big_button bigger" id="{$button.id}">
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
			{$__link.sections.$sk.buttons.$key.link.close}
		{/foreach}
	</div>
{/foreach}
</div>

{$form_close}
