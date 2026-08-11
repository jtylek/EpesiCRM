{if !$logged}

<div id="Base_Box__login">
	<div class="status">{$status}</div>
	<div class="entry">{$login}</div>
</div>

{else}

{php}
	load_js('modules/Base/Box/theme/default.js');
	eval_js_once('document.body.id=null'); //pointer-events:none;
{/php}
	<canvas class="Base_Help__tools" style="height:3000px;width:3000px;" id="help_canvas" width="3000px" height="3000px"></canvas>
	<img class="Base_Help__tools" style="display: none;" id="Base_Help__help_arrow" src="{$theme_dir}/Base/Help/arrow.png" />
	<div class="Base_Help__tools comment" style="display: none;" id="Base_Help__help_comment"><div id="Base_Help__help_comment_contents"></div><div class="button_next" id="Base_Help__button_next">{'Next'|t}</div><div class="button_next" id="Base_Help__button_finish">{'Finish'|t}</div></div>
	<div id="top_bar" class="nonselectable" style="width:100%">
		<div id="MenuBar">
		{* Was a <table> (fixed-width spacer/menu/home/help/module-indicator
		   cells either side of one flexible "filler" cell) - flex row instead,
		   with .filler's own flex:1 1 auto (see this template's CSS) doing the
		   job the spacer-free "auto" column used to do (see
		   AI-shared/adminlte-theme.md). *}
		<div id="top_bar_1" style="display: flex;">
			<div style="width: 8px;"></div>
			<div class="menu-bar">{$menu}</div>
			<div style="width: 7px;"></div>
			<div class="home-bar" {$home.href}>
				<div id="home-bar1">
					<div class="home-bar-icon"></div>
					<div class="home-bar-text">
						{$home.label}
					</div>
				</div>
			</div>
			<div style="width: 6px;"></div>
			<div class="top_bar_black filler"></div>
			<div class="top_bar_black powered">
				<div>
					<a href="http://epe.si" target="_blank" style="color:white;"><b>EPESI</b> powered</a>&nbsp;
				</div>
				<div>{$version_no}</div>
			</div>
			{if isset($donate)}
				<div class="top_bar_black donate" style="white-space: nowrap;">{$donate}</div>
			{/if}
			<div style="width: 6px;"></div>
			<div class="top_bar_black top_bar_help">{$help}</div>
			<div style="width: 6px;"></div>
			<div class="top_bar_black module-indicator"><div id="module-indicator">{if $moduleindicator}{$moduleindicator}{else}&nbsp;{/if}</div></div>
			<div style="width: 8px;"></div>
		</div>
		</div>
		<div id="ShadowBar" style="display: none;"></div>
		<div id="ActionBar">
			{* Was a <table> (fixed-width logo/launchpad/login-search-td cells
			   either side of one flexible "icons" cell) - same recipe as
			   top_bar_1 above, .icons gets the flex:1 1 auto this time. *}
			<div id="top_bar_2" style="display: flex;">
				<div style="width: 8px;"></div>
				<div class="logo"><div class="shadow_css3_logo_border">{$logo}</div></div>
				<div style="width: 6px;"></div>
				<div class="icons">
					<div class="shadow_css3_icons_border">
						{$actionbar}
					</div>
				</div>
				<div id="launchpad_button_section_spacing" style="width: 6px; display:none;"></div>
				<div class="icons_launchpad" id="launchpad_button_section" style="display:none;">
					<div class="shadow_css3_icons_launchpad_border">
						{$launchpad}
					</div>
				</div>
				<div style="width: 6px;"></div>
				<div id="login-search-td">
					<div class="shadow_css3_login-search-td_border">
							<div class="login">{$login}</div>
							<div class="search" id="search_box">{$search}</div>
							<div class="filter" id="filter_box">{$filter}</div>
					</div>
				</div>
				<div style="width: 8px;"></div>
			</div>
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
