{php}
	load_js('modules/Utils/TabbedBrowser/theme/default.js');
{/php}

{* $link/$s_link are full <span id="..." class="tabbed_browser_selected|
   tabbed_browser_unselected" href=... onClick=...>caption<img class="tab_icon"
   ...></span> strings built by TabbedBrowser_0.php - shared with the default
   theme, so only their class names are restyled here, not their markup.
   Submenu positioning (tabbedbrowser_show_submenu/hide_submenu, theme/
   default.js) uses Prototype's clonePosition to place the popup under its
   group's toggle span - unchanged, so the popup div keeps the same
   position:absolute + id="tabbedbrowser_{$cap}_popup" the JS looks up. *}
<div class="epesi-tb">
	<ul class="epesi-tb-nav">
	{foreach from=$captions key=cap item=link}
		{if isset($captions_submenus[$cap])}
			<li class="epesi-tb-item" onmouseover="tabbedbrowser_show_submenu('{$cap}')" onmouseout="tabbedbrowser_hide_submenu('{$cap}')">
				<div class="epesi-tb-submenu tabbedbrowser_submenu" id="tabbedbrowser_{$cap}_popup" style="display:none;position:absolute;">
					{foreach from=$captions_submenus[$cap] key=s_cap item=s_link}
						{$s_link}
					{/foreach}
				</div>
		{else}
			<li class="epesi-tb-item">
		{/if}
		{$link}
		</li>
	{/foreach}
	</ul>
	<div class="epesi-tb-body">{$body}</div>
</div>
