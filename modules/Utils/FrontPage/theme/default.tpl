<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
	  <meta content="text/html; charset=utf-8" http-equiv="content-type">
	  <title>{$title}</title>
	  <link href="{$url}/{$theme_dir}/Utils/FrontPage/default.css" type="text/css" rel="stylesheet"/>
</head>
<body>
		{if $header!==null}
			<div id="banner" style="display: flex;">
				<div class="page_header image"><img src="{$url}/{$logo}" width="193" height="83"></div>
				<div class="page_header" style="flex: 1 1 auto;">{$header}&nbsp;</div>
				<div class="page_header image"></div>
			</div>
			<br>
		{/if}
		<center>
		<div style="display: flex; padding: 10px; column-gap: 8px; width:{if $info}100%{else}800px{/if};">
				<div class="main frame contents">
					{$contents}
				</div>
				{if $info}
					<div class="info frame">
						{$info}
					</div>
				{/if}
		</div>
		</center>
		<br>
		<center>
		<span class="footer">{$footer}</span>
		<br>
		<p><a href="http://www.epesi.org"><img alt="" src="{$url}/images/epesi-powered.png" border="0"></a></p>
		</center>
</body>
</html>
