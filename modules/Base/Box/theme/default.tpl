{if !$logged}

<div id="Base_Box__login">
	<div class="status">{$status}</div>
	<div class="entry">{$login}</div>
</div>

{else}

{php}
	load_js($this->get_template_vars('theme_dir').'/Base/Box/default.js');
	eval_js_once('document.body.id=null');
{/php}

	<div id="top_bar" style="width:100%">
		<div id="MenuBar">
		<table id="top_bar_1" cellspacing="0" cellpadding="0" border="0">
		<tbody>
			<tr>
				<td class="menu-bar">{$menu}</td>
				<td class="powered" nowrap="1"><a href="http://www.epesibim.com" target="_blank" style="color:white;"><b>epesi</b> powered</a> {$version_no}</td>
				{if isset($donate)}
					<td class="donate" nowrap="1">{$donate}</td>
				{/if}
				<td class="module-indicator"><div id="module-indicator">{if $moduleindicator}{$moduleindicator}{else}&nbsp;{/if}</div><div id="quick-logout" style="display: none;"></div></td>
			</tr>
		</tbody>
		</table>
		</div>
		<div id="ShadowBar" style="display: none;"></div>
		<div id="ActionBar">
			<table id="top_bar_2" cellspacing="0" cellpadding="0" border="0">
			<tbody>
				<tr>
					<td class="logo">{$logo}</td>
					<td class="icons">{$actionbar}</td>
					<td id="login-search-td">
						<div id="search-login-bar">
							<div class="login">{$login}</div>
							<div class="search" id="search_box">{$search}</div>
							<div class="filter" id="filter_box">{$filter}</div>
						</div>
					</td>
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
