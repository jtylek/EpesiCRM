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
	<canvas class="Base_Help__tools" style="height:3000px;width:3000px;" id="help_canvas" width="3000px" height="3000px"></canvas>
	<img class="Base_Help__tools" style="display: none;" id="Base_Help__help_arrow" src="{$theme_dir}/Base/Help/arrow.png" />
	<div class="Base_Help__tools comment" style="display: none;" id="Base_Help__help_comment"><div id="Base_Help__help_comment_contents"></div><div class="button_next" id="Base_Help__button_next">{'Next'|t}</div><div class="button_next" id="Base_Help__button_finish">{'Finish'|t}</div></div>
	<div id="top_bar" class="nonselectable" style="width:100%">
		<div id="MenuBar">
		<table id="top_bar_1" cellspacing="0" cellpadding="0" border="0">
		<tbody>
			<tr>
				<td style="empty-cells: hide; width: 8px;"></td>
				<td class="menu-bar">{$menu}</td>
				<td style=" empty-cells: hide; width: 7px;"></td>
				<td class="home-bar" {$home.href}>
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
				<td class="top_bar_black filler"></td>
				<td class="top_bar_black powered" nowrap="1">
					<div>
						<a href="http://epe.si" target="_blank" style="color:white;"><b>EPESI</b> powered</a>&nbsp;
					</div>
					<div>{$version_no}</div>
				</td>
				{if isset($donate)}
					<td class="top_bar_black donate" nowrap="1">{$donate}</td>
				{/if}
				<td style="empty-cells: hide; width: 6px;"></td>
				<td class="top_bar_black top_bar_help">{$help}</td>
				<td style="empty-cells: hide; width: 6px;"></td>				
				<td class="top_bar_black module-indicator"><div id="module-indicator">{if $moduleindicator}{$moduleindicator}{else}&nbsp;{/if}</div></td>
				<td style="empty-cells: hide; width: 8px;"></td>
			</tr>
		</tbody>
		</table>
		</div>
		<div id="ShadowBar" style="display: none;"></div>
		<div id="ActionBar">
			<table id="top_bar_2" cellspacing="0" cellpadding="0" border="0">
			<tbody>
				<tr>
					<td style="empty-cells: hide; width: 8px;"></td>
					<td class="logo"><div class="shadow_css3_logo_border">{$logo}</div></td>
					<td style="empty-cells: hide; width: 6px;"></td>
					<td class="icons">
						<div class="shadow_css3_icons_border">
							{$actionbar}
						</div>
					</td>
					<td id="launchpad_button_section_spacing" style="empty-cells: hide; width: 6px; display:none;"></td>
					<td class="icons_launchpad" id="launchpad_button_section" style="display:none;">
						<div class="shadow_css3_icons_launchpad_border"> 
							{$launchpad}
						</div>
					</td>
					<td style="empty-cells: hide; width: 6px;"></td>
					<td id="login-search-td">
						<div class="shadow_css3_login-search-td_border">
								<div class="login">{$login}</div>
								<div class="search" id="search_box">{$search}</div>
								<div class="filter" id="filter_box">{$filter}</div>
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
		<div id="content_body" style="top: 50px;">
			<center>{$main}</center>
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
