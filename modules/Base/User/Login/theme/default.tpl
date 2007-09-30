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
	    <table id="Base_User_Login" cellspacing="0" cellpadding="0" border="0">
	    	<tr>
				<td colspan="2" class="header_tail">
					<!-- <span align=left class=header>{$form_data.header.login_header}</span> -->
					<img border="0" src="{$theme_dir}/images/logo.png" width="388" height="198">
				</td>
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
				<td colspan=2 align="center">{$form_data.autologin.html}</td>
			</tr>
			<tr><td colspan="2" class="submit_button">{$form_data.submit_button.html}</td></tr>
			<tr><td colspan="2" class="recover_password">{$form_data.recover_password.html}</td></tr>
			<tr><td colspan="2" class="footer">Copyright &copy; 2007 &bull; <a href="http://sourceforge.net/projects/epesi/">epesi framework</a><br>Application developed by <a href="http://www.telaxus.com">Telaxus LLC</a></td></tr>
		</table>
		</center>
	</form>
{/if}	