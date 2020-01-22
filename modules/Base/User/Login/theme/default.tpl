
{if $is_logged_in}
	{$logged_as}
	{$logout}
{else}
	{$form_data.javascript}
	
	<center>
	<div class="content">
	<form {$form_data.attributes}>
	{$form_data.hidden}
    <!-- Display the fields -->


		<div>
	    <table id="Base_User_Login" class="login_box">
            <tbody>
	    	<tr>
				<td colspan="2" class="header_tail">{$logo}</td>
			</tr>
            <tr>
			
			
            <td class="label">
                    <table>
                        <tbody>
						
						
					{if $is_demo}
						<tr>
							<td colspan="2" class="login_box"><strong>Demo mode</strong></td>
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
	</form>
	</center>
	</div>
{/if}


<div class="footer">
			<br><center>
			<!-- Epesi Terms of Use require line below - do not remove it! -->
			<span class="footer">Copyright &copy; 2006-2020 by <a href="https://epe.si">Janusz Tylek</a></span><br>
			<span><a href="http://www.epe.si"><img src="images/epesi-powered.png" border="0"></a></span>
            <!-- Epesi Terms of Use require line above - do not remove it! -->
		</center>
</div>

{literal}
<style type="text/css">
div > div#top_bar { position: fixed;}
div > div#bottom_bar { position: fixed;}
</style>
{/literal}
