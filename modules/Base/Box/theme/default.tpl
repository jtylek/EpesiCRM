{if !$logged}

<div id="Base_Box__login">
	<div class="status">{$status}</div>
	<div class="entry">{$login}</div>
</div>

{else}

<!-- {php}
	load_js($this->get_template_vars('theme_dir').'/Base/Box/default.js');
	eval_js_once('document.body.id=null'); //pointer-events:none;
{/php} -->

	<canvas class="Base_Help__tools" style="height:3000px;width:3000px;" id="help_canvas" width="3000px" height="3000px"></canvas>
	<img class="Base_Help__tools" style="display: none;" id="Base_Help__help_arrow" src="{$theme_dir}/Base/Help/arrow.png" />
	<div class="Base_Help__tools comment" style="display: none;" id="Base_Help__help_comment"><div id="Base_Help__help_comment_contents"></div><div class="button_next" id="Base_Help__button_next">{'Next'|t}</div><div class="button_next" id="Base_Help__button_finish">{'Finish'|t}</div></div>
	
		<div id="top_bar">
				<span div class="logo" {$home.href}>
					<span id="home-bar">
						<span class="home-bar-icon"></span>
						<span class="home-bar-text">
							{$home.label}
						</span>
					</span>
				</span>
				{if $quick_access_menu}
					<span class="quick-access-bar">{$quick_access_menu}</span
				{/if}
					
				<span id="module-indicator">{if $moduleindicator}{$moduleindicator}{else}&nbsp;{/if}</span>
				<span class="top_bar">epesi {$version_no}</span>
				
				<!-- 
				{if isset($donate)}
					<td class="top_bar_black donate" nowrap="1">{$donate}</td>
				{/if}
				 -->
				
				<span class="top_bar">{$help}</span>				
				
		</div>
				
		<div id="ShadowBar" class="ShadowBar"></div>
		
		<div id="ActionBar" class="ActionBar">
					<span class="logo">
						{$logo}
					</span>
					<span class="menu">{$menu}</span>
					<span class="icons">
						<div class="actionbar_shadow">
							{$actionbar}
						</div>
					</span>
					<span class="filter" id="filter_box">{$filter}</span>
					<span class="icons_launchpad" id="launchpad_button_section" style="display:none;">
						<div class="actionbar_shadow">
							{$launchpad}
						</div>
					
					<span id="login-search-td">
						<div class="actionbar_shadow"> 
							<div class="search" id="search_box">{$search}</div>
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
