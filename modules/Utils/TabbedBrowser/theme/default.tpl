{php}
	load_js('modules/Utils/TabbedBrowser/theme/default.js');
{/php}

<div class="Utils_TabbedBrowser_div">

<div style="width: 100%;">
			<ul class="Utils_TabbedBrowser">
			{foreach from=$captions key=cap item=link}
				{if isset($captions_submenus[$cap])}
					<li onmouseover="tabbedbrowser_show_submenu('{$cap}')" onmouseout="tabbedbrowser_hide_submenu('{$cap}')">
						<div class="tabbedbrowser_submenu" id="tabbedbrowser_{$cap}_popup" style="display:none;position:absolute;">
							{foreach from=$captions_submenus[$cap] key=s_cap item=s_link}
								{$s_link}
							{/foreach}
						</div>
				{else}
					<li>
				{/if}
				{$link}
				</li>&nbsp;
			{/foreach}
			</ul>
		<div class="border_bottom"></div>
			<center>{$body}</center>
</div>

</div>
