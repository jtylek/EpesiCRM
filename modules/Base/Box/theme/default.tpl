{if !$logged}

<div id="Base_Box__login">
	<div class="status">{$status}</div>
	<div class="entry">{$login}</div>
	<div class="starting">{$about}</div>
</div>

{else}

{php}
	load_js('data/Base_Theme/templates/default/Base_Box__default.js');
	eval_js_once('document.body.id=null');
{/php}

	<div id="top_bar">
		<table id="top_bar_1" cellspacing="0" cellpadding="0" border="0">
		<tbody>
			<tr>
				<td class="menu-bar">{$menu}</td>
				<td class="module-indicator">{if $moduleindicator}{$moduleindicator}{else}&nbsp;{/if}</td>
			</tr>
		</tbody>
		</table>
		<table id="top_bar_2" cellspacing="0" cellpadding="0" border="0">
		<tbody>
			<tr>
				<td class="logo"><a href="#"><img border="0" src="{$theme_dir}/images/logo-small.png" width="193" height="68"></a></td>
				<td class="icons">{$actionbar}</td>
				<td class="login-search">
					<div class="login">{$login}</div>
					<div class="search"><center>{$search}</center></div>
				</td>
			</tr>
		</tbody>
		</table>
	</div>
	<!-- -->
	<div id="content">
		<div style="height: 102px"></div>
		<div id="content_body" style="padding: 10px;">
			{$main}
		</div>
		<div style="height: 24px"></div>
	</div>
	<!-- -->
	<div id="bottom_bar">
        <table id="footer" cellspacing="0" cellpadding="0" border="0">
		<tbody>
            <tr>
                <td class="left"><a href="http://sourceforge.net/project/showfiles.php?group_id=192918">version {$version_no}</a></td>
                <td class="center">Copyright &copy; 2007 &bull; <a href="http://sourceforge.net/projects/epesi/">epesi framework</a> &bull; Application developed by <a href="http://www.telaxus.com">Telaxus LLC</a></td>
                <td class="right">{$about}</td>
            </tr>
		</tbody>
        </table>
	</div>

{literal}
<style type="text/css">
div > div#top_bar { position: fixed;}
div > div#bottom_bar { position: fixed;}
</style>

<!--[if gte IE 5.5]><![if lt IE 7]>
<style type="text/css">
#top_bar {
	position: absolute;
	width: expression( (body.offsetWidth-20)+'px');
}
#bottom_bar {
	position: absolute;
	width: expression( (body.offsetWidth-20)+'px');
	bottom: -1px;
}
#content_body {
	width: expression( (body.offsetWidth-20)+'px');
}

#content {
	display: block;
	height: 100%;
	max-height: 100%;
	overflow-x: hidden;
	overflow-y: auto;
	position: relative;
	z-index: 0;
	width:100%;
}
html { height: 100%; max-height: 100%; padding: 0; margin: 0; border: 0; overflow:hidden; /*get rid of scroll bars in IE */ }
body { height: 100%; max-height: 100%; border: 0; }

</style>
<![endif]><![endif]-->
{/literal}

{/if}

{$status}
