{if $main_page}
<div class="Utils_Tray__title">
		<div style="width: 100px;">
			<div class="name">
				<img alt="&nbsp;" class="icon" src="{$icon}" width="32" height="32"
					border="0">
				<div class="label">{$caption}</div>
			</div>
		</div>
		<div class="required_fav_info" style="flex: 1 1 auto;"></div>
</div>
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
						<div class="Utils_Tray__box_table">
							<div class="Utils_Tray__box_table_th"><span style="margin-left: 5px">{$box.title}</span></div>
							<div class="Utils_Tray__box_wrap">{foreach from=$box.slots
								item=slot} {$slot} {/foreach}</div>
						</div>
					</div>
					{/foreach}
				</div>
				{if $main_page}
			</div>
		</div>
	</div>
</div>
{/if}
