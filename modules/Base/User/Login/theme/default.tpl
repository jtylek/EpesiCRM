{if $is_logged_in}
{$logged_as}
{$__link.logout.open}
	{$__link.logout.text}&nbsp;<img border="0" width="10" height="10" src="{$theme_dir}/images/logout.png">
{$__link.logout.close}
{else}
	{$form_data.javascript}

	<form {$form_data.attributes}>
	{$form_data.hidden}
    <!-- Display the fields -->
		<center>

<!-- poprawic cien - jako funkcje -->
<table id="shadow" cellpadding="0" cellspacing="0" border="0">
    <tbody>
    <tr>
        <td class="td-5x5 p-bottom top-left">&nbsp;</td>
        <td class="td-h-5 p-bottom top-center">&nbsp;</td>
        <td class="td-5x5 p-bottom top-right">&nbsp;</td>
    </tr>
    <tr>
        <td class="td-w-5 center-left">&nbsp;</td>
        <td class="center-center">
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
                        <tr>
                			<td colspan="2" class="title">Managing Business Your Way <span>TM</span></td>
                        </tr>
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
                        <tr>
                            <td colspan="2" class="autologin">{$form_data.autologin.html}</td>
                        </tr>
                        <tr><td colspan="2" class="submit_button">{$form_data.submit_button.html}</td></tr>
                        <tr><td colspan="2" class="recover_password">{$form_data.recover_password.html}</td></tr>
                        <tr><td colspan="2" class="footer">Copyright &copy; 2007 &bull; <a href="http://www.telaxus.com">Telaxus LLC</a></td></tr>
                        </tbody>
                    </table>
                </td>
            </tr>
            </tbody>
		</table>

        <!-- -->
        </td>
        <td class="td-w-5 center-right">&nbsp;</td>
    </tr>
    <tr>
        <td class="td-5x5 p-top bottom-left">&nbsp;</td>
        <td class="td-h-5 p-top bottom-center">&nbsp;</td>
        <td class="td-5x5 p-top bottom-right">&nbsp;</td>
    </tr>
    </tbody>
</table>




		</center>
	</form>
{/if}
