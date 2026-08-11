{* The mobile/desktop chooser (IPHONE UA -> a "choose mobile or desktop"
   page linking to mobile.php or here with ?force_desktop=1; other mobile
   UAs -> an automatic redirect to mobile.php) was retired 2026-07-28 - the
   adminlte Base_Theme is responsive and handles mobile devices directly,
   so every device now renders this one body unconditionally. See
   [[legacy-mobile-removed]] memory for the full removal. *}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

		<head profile="http://www.w3.org/2005/11/profile">
		<link rel="icon" type="image/png" href="images/favicon.png" />
		<link rel="apple-touch-icon" href="images/pwa/apple-touch-icon.png" />
		<title>{$EPESI}</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		{* Without this a phone renders the page at a ~980px virtual width and
		   scales it down, which defeats any responsive layout. Theme-independent
		   and harmless for the fixed-width default theme. Zoom is deliberately
		   left enabled - no maximum-scale/user-scalable. *}
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<meta name="SKYPE_TOOLBAR" content="SKYPE_TOOLBAR_PARSER_COMPATIBLE" />
        <meta name="robots" content="NOINDEX, NOARCHIVE">
		{* "Add to Home Screen" support - manifest.json declares display:standalone
		   (launches with no browser chrome, incl. the address bar, once added -
		   the only way a page can actually make that disappear, unlike a normal
		   browser tab) and orientation:landscape (reinforces the adminlte theme's
		   own CSS portrait-lock overlay at the OS/browser level, for whichever
		   browsers honour it - Android Chrome does, iOS Safari doesn't yet).
		   Theme-independent and harmless for the default theme, same reasoning
		   as the viewport meta tag above. The apple-mobile-web-app-* meta tags
		   are iOS Safari's own pre-manifest-support mechanism - it still doesn't
		   read display/orientation from manifest.json, only these. *}
		<link rel="manifest" href="manifest.json" />
		<meta name="theme-color" content="#0d6efd" />
		<meta name="apple-mobile-web-app-capable" content="yes" />
		<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
		<meta name="apple-mobile-web-app-title" content="EPESI" />
{$js_tags_html}
		<link type="text/css" href="{$csses_src}" rel="stylesheet"></link>

		<style type="text/css">
			{if $DIRECTION_RTL}body {ldelim} direction: rtl; {rdelim}{/if}
			{if $theme_name == 'adminlte' || $theme_name == 'adminltedark'}
			{literal}
			/* This splash prints before any per-module theme CSS loads (that
			   only happens once Base_Box itself renders, well after this) -
			   so, same reasoning as this session's Utils_Tooltip/e:load
			   incident, it can't assume Bootstrap/AdminLTE's stylesheets are
			   attached yet and has to be fully self-contained plain CSS. */
			#epesiStatus {
				display: flex;
				flex-direction: column;
				position: fixed;
				left: 50%; top: 30%;
				transform: translateX(-50%);
				background-color: #fff;
				border: none;
				visibility: hidden;
				width: 400px;
				max-width: 90vw;
				text-align: center;
				z-index: 2002;
				color: #212529;
				overflow: hidden;
				padding: 1.75rem 1.5rem 1.5rem;
				border-radius: 0.5rem;
				box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.25);
				font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
			}
			#epesiStatus img {
				max-width: 100%;
				height: auto;
			}
			.epesi-boot-text {
				color: inherit;
				font-weight: normal;
				font-family: inherit;
				font-size: 0.95rem;
				margin-top: 0.5rem;
			}
			.epesi-boot-progress {
				width: 100%;
				height: 0.35rem;
				background-color: #dee2e6;
				border-radius: 999px;
				overflow: hidden;
				margin-top: 0.75rem;
			}
			.epesi-boot-progress-bar {
				width: 40%;
				height: 100%;
				background-color: #0d6efd;
				border-radius: 999px;
				animation: epesi-boot-progress-slide 1.1s ease-in-out infinite;
			}
			@keyframes epesi-boot-progress-slide {
				0% { transform: translateX(-100%); }
				100% { transform: translateX(250%); }
			}
			{/literal}
			{if $theme_name == 'adminltedark'}
			{literal}
			/* Matches the sidebar's fixed light-grey/black-text scheme
			   (commit 77d43486) rather than AdminLTE's own [data-bs-theme=dark]
			   colors - this splash has no light/dark toggle (it renders before
			   that JS runs), so it always uses the sidebar's "always" palette. */
			#epesiStatus {
				background-color: #dee2e6;
				color: #000000;
				box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.6);
			}
			.epesi-boot-progress {
				background-color: #adb5bd;
			}
			{/literal}
			{/if}
			{else}
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
			#epesiStatus > div {
				color: #000000;
				font-weight: normal;
				font-family: Tahoma, Verdana, Vera-Sans, DejaVu-Sans;
				font-size: 16px;
				border: 3px solid #FFFFFF;
            }

			{/literal}
			{/if}
		</style>
		{$TRACKING_CODE}
	</head>
	<body {if $DIRECTION_RTL}class="epesi_rtl"{/if} >

		<div id="body_content">
			<div id="main_content" style="display:none;"></div>
			<div id="debug_content" style="padding-top:97px;display:none;">
				<div class="button" onclick="document.getElementById('error_box').innerHTML='';document.getElementById('debug_content').style.display='none';">Hide</div>
				<div id="debug"></div>
				<div id="error_box"></div>
			</div>

			<div id="epesiStatus">
				{if $theme_name == 'adminlte' || $theme_name == 'adminltedark'}
				<img src="images/logo.png" alt="logo" width="550" height="200" border="0">
				<div class="epesi-boot-text"><span id="epesiStatusText">{$STARTING_MESSAGE}</span></div>
				<div class="epesi-boot-progress"><div class="epesi-boot-progress-bar"></div></div>
				{else}
				<div style="width: 100%;">
					<div><img src="images/logo.png" alt="logo" width="550" height="200" border="0"></div>
					<div style="display: flex; align-items: center; justify-content: center; height: 30px;"><span id="epesiStatusText">{$STARTING_MESSAGE}</span></div>
					<div style="display: flex; align-items: center; justify-content: center; height: 30px;"><img src="images/loader.gif" alt="loader" width="256" height="10" border="0"></div>
				</div>
				{/if}
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
