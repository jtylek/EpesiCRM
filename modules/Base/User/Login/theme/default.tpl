
{if $is_logged_in}
	<div class="logged_as">{$logged_as_text}</div>
	<div class="logout_css3_box"><a class="logout_icon" {$logout_href}>{$logout_label}<div class="logout_icon_img"></div></a></div>
{else}
	{$form_data.javascript}

	<form {$form_data.attributes}>
	{$form_data.hidden}
    <!-- Display the fields -->
		<center>

	<div class="layer" style="padding: 9px; width: 552px;">
		<div class="css3_content_shadow">

	    <div id="Base_User_Login" style="height: auto;">
			<div class="header_tail">{$logo}</div>
            <div class="gradient">
                <div style="width:100%;">
{if $is_demo}
   			<div class="demo_notice"><strong>EPESI DEMO APPLICATION</strong></div>
{/if}
					{if isset($message)}
						<div class="message">
							{$message}
						</div>
						<div class="autologin"></div>
					{else}
						{if $mode=='recover_pass'}
                            <div class="error"><span class="error">{$form_data.username.error}</span></div>
                            <div class="error"><span class="error">{$form_data.mail.error}</span></div>
							<div class="login-row">
								<div class="label">{$form_data.username.label}&nbsp;&nbsp;</div>
								<div class="input">{$form_data.username.html}</div>
							</div>
							<div class="login-row">
								<div class="label">{$form_data.mail.label}&nbsp;&nbsp;</div>
								<div class="input">{$form_data.mail.html}</div>
							</div>
							<div class="submit_button">{$form_data.buttons.html}</div>
							<div class="autologin"></div>
						{else}
                            <div class="error"><span class="error">{$form_data.username.error}</span></div>
                            <div class="error"><span class="error">{$form_data.password.error}</span></div>
							<div class="login-row">
								<div class="label">{$form_data.username.label}&nbsp;&nbsp;</div>
								<div class="input">{$form_data.username.html}</div>
							</div>
							<div class="login-row">
								<div class="label">{$form_data.password.label}&nbsp;&nbsp;</div>
								<div class="input">{$form_data.password.html}</div>
							</div>
							<div class="submit_button">{$form_data.submit_button.html}</div>
							<div class="autologin">{$form_data.autologin.html}</div>
						{/if}
					{/if}
						<div class="autologin">{$form_data.warning.html}</div>
                        <div class="recover_password">{$form_data.recover_password.html}</div>
                        <div>&nbsp;</div>
                        <div class="footer">
                        <!-- Epesi Terms of Use require line below - do not remove it! -->
                        Copyright &copy; 2006-{php}echo date("Y"){/php} by Janusz Tylek
                        <!-- Epesi Terms of Use require line above - do not remove it! -->
                        </div>
                </div>
            </div>
		</div>

 		</div>
	</div>

            <!-- Epesi Terms of Use require line below - do not remove it! -->
            <a href="http://epe.si/"><img src="images/epesi-powered.png" alt="EPESI powered" /></a>
            <!-- Epesi Terms of Use require line above - do not remove it! -->

		</center>
	</form>
{/if}





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

#body_content {
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
	bottom: -3px;
}

.layer .shadow-top div.center {
	top: -2px;
}

</style>

<![endif]><![endif]-->

{/literal}
