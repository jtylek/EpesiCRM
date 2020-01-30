{php}
	load_js($this->get_template_vars('theme_dir').'/Utils/TabbedBrowser/default.js');
{/php}

<div class="Utils_TabbedBrowser_div">

<table class="tabbed_browser">
	<tr>
		<td>
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
		</td>
	</tr>
	<tr >
		<td >
		<div class="border_bottom"></div>
			{$body}
		</td>
	</tr>
</table>

</div>
