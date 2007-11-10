{literal}

<!--[if gte IE 5.5]><![if lt IE 7]>
<style type="text/css">

.layer .left,
.layer .right,
.layer .center {
	background: none !important;
}

.layer .shadow-middle div {
	height: expression(
		x = this.parentNode.parentNode.offsetHeight,
		y = parseInt(this.currentStyle.top),
		(x - ((x % 2) ? 1 : 0) - (y * 2)) + 'px'
	)
}

.layer .shadow-top .center,
.layer .shadow-bottom .center {
	width: expression(
		x = this.parentNode.parentNode.offsetWidth,
		y = parseInt(this.currentStyle.left),
		(x - ((x % 2) ? 1 : 0) - (y * 2)) + 'px'
	)
}
																								/* POPRAWIC SCIEZKE ! */
.layer .shadow-top .left		{ filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src="modules/Base/Theme/images/shadow/tl.png", sizingMethod="crop");  }
.layer .shadow-top .right		{ filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src="modules/Base/Theme/images/shadow/tr.png", sizingMethod="crop");  }
.layer .shadow-bottom .left		{ filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src="modules/Base/Theme/images/shadow/bl.png", sizingMethod="crop");  }
.layer .shadow-bottom .right	{ filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src="modules/Base/Theme/images/shadow/br.png", sizingMethod="crop");  }
.layer .shadow-top .center		{ filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src="modules/Base/Theme/images/shadow/t.png",  sizingMethod="scale"); }
.layer .shadow-bottom .center	{ filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src="modules/Base/Theme/images/shadow/b.png",  sizingMethod="scale"); }
.layer .shadow-middle .left		{ filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src="modules/Base/Theme/images/shadow/l.png",  sizingMethod="scale"); }
.layer .shadow-middle .right	{ filter: progid:DXImageTransform.Microsoft.AlphaImageLoader(src="modules/Base/Theme/images/shadow/r.png",  sizingMethod="scale"); }

.layer .shadow-bottom div.center {
	bottom: -2px;
}

.layer .shadow-top div.center {
	top: -2px;
}

</style>
<![endif]><![endif]-->


{/literal}


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
				<td class="version"><a href="http://sourceforge.net/project/showfiles.php?group_id=192918">&nbsp;version&nbsp;{$version_no}&nbsp;</a></td>
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
					<div class="login"><br>{$login}</div>
					<div class="search"><center>{$search}</center></div>
				</td>
			</tr>
		</tbody>
		</table>
	</div>
	<!-- -->
	<div id="content">
		<div style="height: 102px"></div>
		<div id="content_body" style="padding: 10px; text-align: center;">
			<center>{$main}</center>
		</div>
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
