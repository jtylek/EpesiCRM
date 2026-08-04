{$form_open}

<div style="max-width:900px" id="Base_Admin__access">
{foreach from=$sections key=sk item=s}
	<div class="epesi_label header" style="clear:both;">{$s.header}</div>
    <div class="buttons_container">
		{foreach key=key item=button from=$s.buttons}
			{$__link.sections.$sk.buttons.$key.link.open}
				<div class="epesi_big_button bigger" id="{$button.id}">
				{* Was a <table> with a <td rowspan="2"> form-switch cell beside
				   icon/text rows, plus a full-width sections row containing an
				   inner label/data <table> - CSS Grid instead, grid-row:span 2
				   replacing the rowspan (see AI-shared/adminlte-theme.md). *}
				<div role="table" style="display: grid; grid-template-columns: auto auto;">
					<div class="bb_icon" role="cell">
						{if isset($button.icon)}
						<img src="{$button.icon}" border="0" width="32" height="32" align="middle">
						{/if}
					</div>
					<div class="bb_form" role="cell" style="grid-row: span 2;">
						{assign var=button_switch value=$button.enable_switch}
						{$form_data.$button_switch.label}
						{$form_data.$button_switch.html}
					</div>
					<div class="bb_text" role="cell">
						{$button.label}
					</div>
					<div role="cell" style="grid-column: span 2;">
						<div id="{$button.sections_id}">
							<div role="table" style="display: grid; grid-template-columns: auto 1fr;">
								{if !empty($button.sections)}
									{foreach key=section_key item=section from=$button.sections}
										<div role="cell" style="text-align:right;">
											{$form_data.$section.label}
										</div>
										<div role="cell">
											{$form_data.$section.html}
										</div>
									{/foreach}
								{/if}
							</div>
						</div>
					</div>
				</div>
				</div>
			{$__link.sections.$sk.buttons.$key.link.close}
		{/foreach}
	</div>
{/foreach}
</div>

{$form_close}
