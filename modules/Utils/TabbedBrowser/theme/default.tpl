{php}
	load_js($this->get_template_vars('theme_dir').'/Utils/TabbedBrowser/default.js');
{/php}

<div class="Utils_TabbedBrowser_div">

			<ul class="nav nav-tabs">
			{foreach from=$captions key=cap item=link}
				{if isset($captions_submenus[$cap])}
					<li role="presentation" onmouseover="tabbedbrowser_show_submenu('{$cap}')" onmouseout="tabbedbrowser_hide_submenu('{$cap}')">
						<div class="tabbedbrowser_submenu" id="tabbedbrowser_{$cap}_popup" style="display:none;position:absolute;">
							{foreach from=$captions_submenus[$cap] key=s_cap item=s_link}
								{$s_link.link}
							{/foreach}
						</div>
				{else}
					<li role="presentation" {if $link.selected}class="active"{/if}>
				{/if}
				{$link.link}
				</li>&nbsp;
			{/foreach}
			</ul>
			<div class="well">
				{$body}
			</div>

</div>
