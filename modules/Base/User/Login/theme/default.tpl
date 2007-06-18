{if $is_logged_in}
	{$logged_as}
	{$__link.logout.open}
	{$__link.logout.text}
	{$__link.logout.close}
{else}
	{$form_data.javascript}

	<form {$form_data.attributes}> 
	{$form_data.hidden}
    <!-- Display the fields -->
	    <table class=Base_User_Login>
	    	<tr>
				<td colspan=2 class=header_tail>
					<span align=left class=header>{$form_data.header.login_header}</span>
				</td>
			</tr>
			{if $form_data.username.error}
				<tr><td colspan=2><span class=error>{$form_data.username.error}</span></td></tr>
			{/if}
			<tr>
				<td align=right class=label>{$form_data.username.label}</td>
				<td align=left>
					{$form_data.username.html}
				</td>
			</tr>
			{if $form_data.username.error}
				<tr><td colspan=2><span class=error>{$form_data.password.error}</span></td></tr>
			{/if}
			<tr>
				<td align=right class=label>{$form_data.password.label}</td>
				<td align=left>
					{$form_data.password.html}
				</td>
			</tr>
			<tr><td colspan=2>{$form_data.recover_password.html}</td></tr>
			<tr><td colspan=2>{$form_data.submit_button.html}</td></tr>
		</table>
	</form>
{/if}