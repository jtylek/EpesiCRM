{if $is_logged_in}
	<div>
		{$logged_as}
		<button class="btn btn-warning pull-right" {$logout_href}>{$logout_label} <i class="glyphicon glyphicon-log-out"></i> </button>
	</div>

{else}
<div style="margin-top: 50px" class="col-md-4 col-md-offset-4 panel">
	<div class="panel-body">
	{$form_data.javascript}

	<form {$form_data.attributes}>
	{$form_data.hidden}
    <!-- Display the fields -->

		<div>{$logo}</div>
{if $is_demo}
   				<div><strong>EPESI DEMO APPLICATION</strong></div>
{/if}
					{if isset($message)}
							<div class="message">
								{$message}
							</div>
					{else}
						{if $mode=='recover_pass'}
                            <span class="error">{$form_data.username.error}</span>
                            <span class="error">{$form_data.mail.error}</span>
							<div class="form-group">
								<label>{$form_data.username.label}</label>
								{$form_data.username.html}
							</div>
							<div class="form-group">
								<label>{$form_data.mail.label}&nbsp;&nbsp;</label>
								{$form_data.mail.html}
							</div>
							{$form_data.buttons.html} //TODO
						{else}
							<span class="error">{$form_data.username.error}</span>
							<span class="error">{$form_data.mail.error}</span>
							<div class="form-group">
								<label>{$form_data.username.label}</label>
								{$form_data.username.html}
							</div>
							<div class="form-group">
								<label>{$form_data.password.label}</label>
								{$form_data.password.html}
							</div>
							{$form_data.submit_button.html}
							<div class="checkbox">
								<label>{$form_data.autologin.html}</label>
								<small>{$form_data.warning.html}</small>
							</div>
						{/if}
					{/if}
                        <p>{$form_data.recover_password.html}</p>
					{if isset($donation_note)}
							<p>{$donation_note}</p>
					{/if}
                        <!-- Epesi Terms of Use require line below - do not remove it! -->
                        <p class="text-center">Copyright &copy; {php}echo date("Y"){/php} &bull; <a href="http://www.telaxus.com">Telaxus LLC</a> &bull; Managing Business Your Way<sup>TM</sup></p>
                        <!-- Epesi Terms of Use require line above - do not remove it! -->

            <!-- Epesi Terms of Use require line below - do not remove it! -->
            <p class="text-center"><a href="http://epe.si/"><img src="images/epesi-powered.png" alt="EPESI powered" /></a></p>
            <!-- Epesi Terms of Use require line above - do not remove it! -->

	</form>
{/if}
</div>
	</div>