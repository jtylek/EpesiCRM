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
	
	<div id="top_bar" class="nonselectable" style="width:100%"></div>
		<div id="MenuBar"></div>
		<table>
			<tbody>
			<tr>
				<td class="menu-bar">
					<div class="menu-bar">{$menu}</div>
				</td>
				<td class="top_bar">
				<div class="home-bar" {$home.href}>
					<div id="home-bar">
						<div class="home-bar-icon"></div>
						<div class="home-bar-text">
							{$home.label}
						</div>
					</div>
				</div>
				</td>
				{if $quick_access_menu}
					<td class="quick-access-bar">{$quick_access_menu}</td>
				{/if}
				<td class="top_bar"><div class="login">{$login}</div></td>
				<td class="top_bar">
					
<!-- 					
					<div>
						<a href="http://epe.si" target="_blank" style="color:white;"><b>EPESI</b> powered</a>&nbsp;
					</div>
 -->					
					<div id="module-indicator">{if $moduleindicator}{$moduleindicator}{else}&nbsp;{/if}</div>
				</td>
				<td class="top_bar">
					<div>epesi {$version_no}</div>
				</td>
				<!-- 
				{if isset($donate)}
					<td class="top_bar_black donate" nowrap="1">{$donate}</td>
				{/if}
				 -->
				<td class="top_bar">{$help}</td>				
				<td class="top_bar"><div class="filter" id="filter_box">{$filter}</div></td>
			</tr>
		</tbody>
		</table>
		</div>
		<div id="ShadowBar" style="display: none;"></div>
		<div id="ActionBar">
			<table id="action_bar_table">
			<tbody>
				<tr>
					<td class="logo">
						<div class="actionbar_shadow">
						{$logo}
						</div>
					</td>
					<td class="icons">
						<div class="actionbar_shadow">
							{$actionbar}
						</div>
					</td>
					<td class="icons_launchpad" id="launchpad_button_section" style="display:none;">
						<div class="actionbar_shadow">
							{$launchpad}
						</div>
					</td>
					<td id="login-search-td">
						<div class="actionbar_shadow"> 
						
								<div class="search" id="search_box">{$search}</div>
								
						</div>	
					</td>
				</tr>
			</tbody>
			</table>
		</div>
	</div>
	<!-- -->
	<div id="content" class="content">
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
