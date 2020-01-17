
{if $is_logged_in}
	{$logged_as}
	{$logout}
{else}
	{$form_data.javascript}

	<form {$form_data.attributes}>
	{$form_data.hidden}
    <!-- Display the fields -->
		<center>

	<div class="layer" style="padding: 30px; width: 550px;">
		<div>

	    <table id="Base_User_Login" cellspacing="0" cellpadding="0" border="0" style="height: 400px;">
            <tbody>
	    	<tr>
				<td colspan="2" class="header_tail">{$logo}</td>
			</tr>
            <tr>
                <td class="gradient">
                    <table cellspacing="0" cellpadding="0" border="0" style="width:100%;table-layout: auto;">
                        <tbody>
{if $is_demo}
   			<tr>
   				<td colspan="2" align="center"><strong>EPESI DEMO APPLICATION</strong></td>
   			</tr>
{/if}
					{if isset($message)}
						<tr>
							<td class="message">
								{$message}
							</td>
						</tr>
						<tr>
							<td colspan="2" class="autologin"></td>
						</tr>
					{else}
						{if $mode=='recover_pass'}
                            <tr><td colspan="2" class="error"><span class="error">{$form_data.username.error}</span></td></tr>
                            <tr><td colspan="2" class="error"><span class="error">{$form_data.mail.error}</span></td></tr>
							<tr>
								<td class="label">{$form_data.username.label}&nbsp;&nbsp;</td>
								<td class="input">{$form_data.username.html}</td>
							</tr>
							<tr>
								<td class="label">{$form_data.mail.label}&nbsp;&nbsp;</td>
								<td class="input">{$form_data.mail.html}</td>
							</tr>
							<tr><td colspan="2" class="submit_button">{$form_data.buttons.html}</td></tr>
							<tr>
								<td colspan="2" class="autologin"></td>
							</tr>
						{else}
                            <tr><td colspan="2" class="error"><span class="error">{$form_data.username.error}</span></td></tr>
                            <tr><td colspan="2" class="error"><span class="error">{$form_data.password.error}</span></td></tr>
							<tr>
								<td class="label">{$form_data.username.label}&nbsp;&nbsp;</td>
								<td class="input">{$form_data.username.html}</td>
							</tr>
							<tr>
								<td class="label">{$form_data.password.label}&nbsp;&nbsp;</td>
								<td class="input">{$form_data.password.html}</td>
							</tr>
							<tr>
								<td colspan="2" class="submit_button">{$form_data.submit_button.html}</td>
							</tr>
							<tr>
								<td colspan="2" class="autologin">{$form_data.autologin.html}</td>
							</tr>
						{/if}
					{/if}
						<tr>
							<td colspan="2" class="autologin">{$form_data.warning.html}</td>
                        </tr>
                        <tr><td colspan="2" class="recover_password">{$form_data.recover_password.html}</td></tr>
                        </tbody>
                    </table>
                </td>
            </tr>
            </tbody>
		</table>

 		</div>
	</div>
            <!-- Epesi Terms of Use require line below - do not remove it! -->
			<span class="footer">Copyright &copy; 2006-2020 by <a href="https://epe.si">Janusz Tylek</a>  &bull; MIT License</span>
			<p><a href="http://www.epe.si"><img src="images/epesi-powered.png" border="0"></a></p>
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

.layer .shadow-top div.center {
	top: -2px;
}

</style>

<![endif]><![endif]-->

{/literal}
