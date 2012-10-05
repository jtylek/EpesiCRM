<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
	  <meta content="text/html; charset=utf-8" http-equiv="content-type">
	  <title>{$title}</title>
	  <link href="{$url}/{$theme_dir}/Utils/FrontPage/default.css" type="text/css" rel="stylesheet"/>
</head>
<body>
		{if $header!==null}
			<table id="banner" border="0" cellpadding="0" cellspacing="0">
				<tr>
					<td class="page_header image"><img src="{$url}/{$logo}" width="193" height="83"></td>
					<td class="page_header">{$header}&nbsp;</td>
					<td class="page_header image"></td>
				</tr>
			</table>
			<br>
		{/if}
		<center>
		<table border="0" cellpadding="10" cellspacing="8" style="width:{if $info}100%{else}800px{/if}; vertical-align: top;">
			<tr>
				<td class="main frame contents" rowspan="2">
					{$contents}
				</td>
				{if $info}
					<td class="info frame">
						{$info}
					</td>
				{/if}
			</tr>
		</table>
		</center>
		<br>
		<center>
		<span class="footer">{$footer}</span>
		<br>
		<p><a href="http://www.epesi.org"><img alt="" src="{$url}/images/epesi-powered.png" border="0"></a></p>
		</center>
</body>
</html>
