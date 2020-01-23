{if $is_logged_in}
	{$logged_as}
	{$logout}
{else}
	{$form_data.javascript}

<div class="header_tail">{$logo}</div>

<div class="content">

	<form {$form_data.attributes}>
	{$form_data.hidden}
    <!-- Display the fields -->


	<div id="Base_User_Login" class="login_box"></div>

	{if $is_demo}
			<div class="login_box"><strong>Demo mode</strong></div>
	{/if}
					
	{if isset($message)}
			<div class="message">{$message}</div>

	{else}

		{if $mode=='recover_pass'}
            
			<div class="error">{$form_data.username.error}</div>
            <div class="error">{$form_data.mail.error}</div>
							
			<div class="label">{$form_data.username.label}</div>
			<div class="input">{$form_data.username.html}</div>
							
							
			<div class="label">{$form_data.mail.label}</div>
			<div class="input">{$form_data.mail.html}</div>
							
			<div class="submit_button">{$form_data.buttons.html}</div>
							
		
		{else}
            
		<div class="username">
			<span class="error">{$form_data.username.error}</span>
			<span class="label">{$form_data.username.label}</span>
			<span class="input">{$form_data.username.html}</span>
		</div>
		
		<div class="password">
			<span class="error">{$form_data.password.error}</span>
			<span class="label">{$form_data.password.label}</span>
			<span class="input">{$form_data.password.html}</span>
		</div>
		
			<div class="submit_button">{$form_data.submit_button.html}</div>
			<div class="autologin">{$form_data.autologin.html}</div>
		{/if}
	{/if}
			
		<div class="autologin">{$form_data.warning.html}</div>
		<div class="recover_password">{$form_data.recover_password.html}</div>	
	</div> <!-- <div id="Base_User_Login" class="login_box"> -->
	
	</form>
</div>  <!-- <div class="content"> -->
{/if}


<div class="footer">
			<!-- Epesi Terms of Use require line below - do not remove it! -->
			<span id="footer_logo"><a href="http://www.epe.si"><img src="images/epesi-powered.png"></a></span>
			<br>
			<span id="footer_text">Copyright &copy; 2006-2020 by Janusz Tylek</span>
            <!-- Epesi Terms of Use require line above - do not remove it! -->
</div>
{/if}

{literal}

{/literal}
