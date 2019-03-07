{if $main_page}
<table class="Utils_Tray__title" border="0" cellpadding="0"
	cellspacing="0">
	<tbody>
		<tr>
			<td style="width: 100px;">
				<div class="name">
					<img alt="&nbsp;" class="icon" src="{$icon}" width="32" height="32"
						border="0">
					<div class="label">{$caption}</div>
				</div>
			</td>
			<td class="required_fav_info"></td>
		</tr>
	</tbody>
</table>
<br>
<div class="table">
	<div class="layer">
		<div class="css3_content_shadow">
			<div class="margin2px">
				{/if}
				<div class="Utils_Tray__wrap">
					{foreach from=$boxes item=box}
					<div style="width: {math equation="
						(100/$box_cols)"}%" class="Utils_Tray__box">
						<table class="Utils_Tray__box_table">
							<thead>
								<th><span style="margin-left: 5px">{$box.title}</span></th>
							</thead>
							<tbody>
								<tr>
									<td>
										<div class="Utils_Tray__box_wrap">{foreach from=$box.slots
											item=slot} {$slot} {/foreach}</div>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
					{/foreach}
				</div>
				{if $main_page}
			</div>
		</div>
	</div>
</div>
{/if}
