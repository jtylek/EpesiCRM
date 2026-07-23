{if $show_iphone_prompt}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <meta name="robots" content="NOINDEX, NOARCHIVE">
	<meta id="viewport" name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;" />
	<title>{$EPESI}</title>
	<link rel="stylesheet" href="libs/UiUIKit/stylesheets/iphone.css" />
	<link rel="apple-touch-icon" href="images/apple-touch-icon.png" />
	{literal}
	<script type="text/javascript" charset="utf-8">
		window.onload = function() {
		  setTimeout(function(){window.scrollTo(0, 1);}, 100);
		}
	</script>
	{/literal}
</head>

<body>
<div id="header">
		<h1>{$EPESI}</h1>
</div>

Please choose {$EPESI} version:<ul>
<li><a href="mobile.php" class="white button">mobile</a><br>
<li><a href="index.php?force_desktop=1" class="green button">desktop</a>
</ul>

</body>
</html>
{else}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

		<head profile="http://www.w3.org/2005/11/profile">
		<link rel="icon" type="image/png" href="images/favicon.png" />
		<link rel="apple-touch-icon" href="images/apple-favicon.png" />
		<title>{$EPESI}</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<meta name="SKYPE_TOOLBAR" content="SKYPE_TOOLBAR_PARSER_COMPATIBLE" />
        <meta name="robots" content="NOINDEX, NOARCHIVE">
{$js_tags_html}
		<link type="text/css" href="{$csses_src}" rel="stylesheet"></link>

		<style type="text/css">
			{if $DIRECTION_RTL}body {ldelim} direction: rtl; {rdelim}{/if}
			{literal}
			#epesiStatus {
  				/* Netscape 4, IE 4.x-5.0/Win and other lesser browsers will use this */
  				position: fixed;
  				left: 50%; top: 30%;
                margin-left: -280px;
  				/* all */
  				/*background-color: #e6ecf2;*/
  				background-color: white;
				border: 3px solid #33993a;
				visibility: hidden;
				width: 560px;
				text-align: center;
				vertical-align: middle;
				z-index: 2002;
                color: #33993a;
				overflow: hidden;

				/* css3 shadow border*/
				-webkit-box-shadow: 1px 1px 15px black;
				-moz-box-shadow: 1px 1px 15px black;
				box-shadow: 1px 1px 15px black;
				/* end css3 shadow border*/

				/* border radius */
				-webkit-border-radius: 10px;
				-moz-border-radius: 10px;
				border-radius: 10px;
				/* end border radius */
			}
			#epesiStatus table {
				color: #000000;
				font-weight: normal;
				font-family: Tahoma, Verdana, Vera-Sans, DejaVu-Sans;
				font-size: 16px;
				border: 3px solid #FFFFFF;
            }

			{/literal}
		</style>
		{$TRACKING_CODE}
	</head>
	<body {if $DIRECTION_RTL}class="epesi_rtl"{/if} >

		<div id="body_content">
			<div id="main_content" style="display:none;"></div>
			<div id="debug_content" style="padding-top:97px;display:none;">
				<div class="button" onclick="$('error_box').innerHTML='';$('debug_content').style.display='none';">Hide</div>
				<div id="debug"></div>
				<div id="error_box"></div>
			</div>

			<div id="epesiStatus">
				<table cellspacing="0" cellpadding="0" border="0" style="width: 100%;">
					<tr>
						<td><img src="images/logo.png" alt="logo" width="550" height="200" border="0"></td>
					</tr>
					<tr>
						<td style="text-align: center; vertical-align: middle; height: 30px;"><span id="epesiStatusText">{$STARTING_MESSAGE}</span></td>
					</tr>
					<tr>
						<td style="text-align: center; vertical-align: middle; height: 30px;"><img src="images/loader.gif" alt="loader" width="256" height="10" border="0"></td>
					</tr>
				</table>
			</div>
		</div>
        {*
         * init_js file allows only num_of_clients sessions. If there is image
         * with empty src="" browser will load index.php file, so we cannot
         * include init_js file directly because num_of_clients request will
         * reset our history and restart EPESI.
         *
         * Check here if request accepts html. If it does we can assume that
         * this is request for page and include init_js file which is faster.
         * If there is not 'html' in accept use script with src property.
         *}
        {if $accepts_html}
		<script type="text/javascript">{$init_js_inline}</script>
        {else}
		<script type="text/javascript" src="init_js.php?{$get_query_string}"></script>
        {/if}
        <noscript>Please enable JavaScript in your browser and let {$EPESI} work!</noscript>
		{if $IPHONE}
		<script type="text/javascript">var iphone=true;</script>
		{/if}
	</body>
</html>
{/if}
