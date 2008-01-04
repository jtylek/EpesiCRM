{if $is_logged_in}
{$logged_as}
{*
{$__link.logout.open}
	{$__link.logout.text}
{$__link.logout.close}
*}
{$logout}
{else}
	{$form_data.javascript}

	<form {$form_data.attributes}>
	{$form_data.hidden}
    <!-- Display the fields -->
		<center>

<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 9px; width: 552px;">
		<div class="content_shadow">
<!-- -->

	    <table id="Base_User_Login" cellspacing="0" cellpadding="0" border="0">
            <tbody>
	    	<tr>
				<td colspan="2" class="header_tail"><img border="0" src="{$theme_dir}/images/logo.png" width="550" height="200"></td>
			</tr>
            <tr>
                <td class="gradient">
                    <table cellspacing="0" cellpadding="0" border="0">
                        <tbody>
                            <tr><td colspan="2" class="error"><span class="error">{$form_data.username.error}</span></td></tr>
                            <tr><td colspan="2" class="error"><span class="error">{$form_data.password.error}</span></td></tr>
                        <tr>
                            <td align="right" class="label">{$form_data.username.label}&nbsp;&nbsp;</td>
                            <td align="left" class="input">{$form_data.username.html}</td>
                        </tr>
                        <tr>
                            <td align="right" class="label">{$form_data.password.label}&nbsp;&nbsp;</td>
                            <td align="left" class="input">{$form_data.password.html}</td>
                        </tr>
                        <tr><td colspan="2" class="submit_button">{$form_data.submit_button.html}</td></tr>
                        <tr>
                            <td colspan="2" class="autologin">{$form_data.autologin.html}</td>
                        </tr>
			<tr>
			    <td colspan="2" class="autologin">{$form_data.warning.html}</td>
                        </tr>
                        <tr><td colspan="2" class="recover_password">{$form_data.recover_password.html}</td></tr>
                        <tr><td>&nbsp;</td></tr>
                        <tr><td colspan="2" class="footer">Copyright &copy; 2007 &bull; <a href="http://www.telaxus.com">Telaxus LLC</a> &bull; Managing Business Your Way <span>TM</span></td></tr>
                        </tbody>
                    </table>
                </td>
            </tr>
            </tbody>
		</table>

<!-- SHADOW END -->
 		</div>
		<div class="shadow-top">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-middle">
			<div class="left"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-bottom">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
	</div>
<!-- -->



		</center>
	</form>
{/if}
