{if !$logged}

<div id="logins">
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

				<div class="logo" {$home.href}>
					<span id="home-bar">
						<span class="home-bar-icon"></span>
						<span class="home-bar-text">{$home.label}</span>
					</span>
				</div>
				
				
				{if $quick_access_menu}
					<span class="quick-access-bar">{$quick_access_menu}</span>
				{/if}
					
				<div id="module-indicator">
						<span id="module-indicator">{if $moduleindicator}{$moduleindicator}</span>{else}</span>{/if}
				</div>
				<div class="epesi_version">epesi {$version_no}</div>
				
				{if isset($donate)}
					<div class="donate">{$donate}</div>
				{/if}
				
				<div class="top_bar">{$help}</div>				
				
		</div>
				
		/********START*********ShadowBar********************/
		<div class="ShadowBar" id="ShadowBar">
		
			<div class="ActionBar" id="ActionBar">
					
					<div class="logo" id="logo">{$logo}</div>
					<div id="menu">{$menu}</div>
					<div class="icons">
						<div class="actionbar_shadow">
							{$actionbar}
						</div>
					</div>
					<div class="filter" id="filter_box">{$filter}</div>
					<div class="icons_launchpad" id="launchpad_button_section" style="display:none;">
						<div class="actionbar_shadow">
							{$launchpad}
						</div>
					</div>
					<div id="login-search-td">
						<div class="actionbar_shadow"> 
							<div class="search" id="search_box">{$search}</div>
						</div>	
					</div>
			</div>		
		</div>
		/*****************ShadowBar**END******************/
	
	<!-- content div -->
	<div id="content" class="content">
		<div id="content_body">{$main}</div>
	</div>

	<div class="status">{$status}</div>


{literal}
<style type="text/css">
div > div#top_bar { position: fixed;}
div > div#bottom_bar { position: fixed;}
</style>

{/literal}

{/if}
