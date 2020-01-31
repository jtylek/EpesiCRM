{if !$logged}

<div id="Base_Box__login">
	<div class="status">{$status}</div>
	<div class="entry">{$login}</div>
</div>

{else}

{php}
	load_js($this->get_template_vars('theme_dir').'/Base/Box/default.js');
	eval_js_once('document.body.id=null'); //pointer-events:none;
{/php}

	<div id="top_bar" class="nonselectable">
		<div id="MenuBar">
		<table id="top_bar_1">
		<tbody>
			<tr>
				<td style="empty-cells: hide; width: 8px;"></td>
				<td class="menu-bar">{$menu}</td>
				<td style=" empty-cells: hide; width: 7px;"></td>
				<td class="home-bar" {$home.href} style="width: 150px;">
					<div id="home-bar1">
						<div class="home-bar-icon"></div>
						<div class="home-bar-text">
							{$home.label}
						</div>
					</div>
				</td>
				<td style="empty-cells: hide; width: 6px;"></td>
				{if $quick_access_menu}
					<td class="quick-access-bar">{$quick_access_menu}</td>
					<td style="empty-cells: hide; width: 6px;"></td>
				{/if}
				
				<td class="top_bar_black module-indicator"><div id="module-indicator">{if $moduleindicator}{$moduleindicator}{else}&nbsp;{/if}</div></td>
				<td class="top_bar_black filler"><div class="filter" id="filter_box">{$filter}</div></td>
				<td style="empty-cells: hide; width: 6px;"></td>
				<td class="top_bar_black module-indicator"><div class="search" id="search_box">{$search}</div></td>
				<td style="empty-cells: hide; width: 8px;"></td>
			</tr>
		</tbody>
		</table>
		</div>
		
		<div id="ActionBar">
			<table id="top_bar_2">
			<tbody>
				<tr>
					<td style="empty-cells: hide; width: 8px;"></td>
					<td class="logo"><div class="shadow_css3_logo_border">{$logo}</div></td>
					<td style="empty-cells: hide; width: 6px;"></td>
				
					<td id="launchpad_button_section_spacing" style="empty-cells: hide; width: 6px; display:none;"></td>
					<td class="icons_launchpad" id="launchpad_button_section" style="display: none;">
						<div class="shadow_css3_icons_launchpad_border"> 
							{$launchpad}
						</div>
					</td>

					<td class="icons">
						<!--<div class="login">{$login}</div>-->
						<div class="shadow_css3_icons_border">
							{$actionbar}
						</div>
					</td>
					<td style="empty-cells: hide; width: 8px;"></td>
				</tr>
			</tbody>
			</table>
		</div>
	</div>
	<!-- -->
	<div id="content">
		<div id="content_body">
			{$main}
		</div>
	</div>

{$status}

{literal}
<style type="text/css">
div > div#top_bar { position: fixed;}
div > div#bottom_bar { position: fixed;}
</style>

{/literal}

{/if}
