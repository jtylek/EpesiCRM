{if $is_logged_in}
	{$logged_as}
	{$logout}
{else}
{$form_data.javascript}
<div class="login-page-adminlte d-flex align-items-center justify-content-center">
	<div class="login-box">
		<div class="card">
			<div class="card-body login-card-body">
				<div class="login-logo">
					{$logo}
				</div>
{if $is_demo}
				<div class="alert alert-warning text-center py-1 mb-3"><strong>EPESI DEMO APPLICATION</strong></div>
{/if}
{if isset($message)}
				<p class="login-box-msg">{$message}</p>
{else}
	<form {$form_data.attributes}>
	{$form_data.hidden}
	{if $mode=='recover_pass'}
				<p class="login-box-msg">{$login_box_msg}</p>
				<div class="text-danger small mb-1">{$form_data.username.error}</div>
				<div class="input-group mb-3">
					{$form_data.username.html}
					<div class="input-group-text"><i class="bi bi-person"></i></div>
				</div>
				<div class="text-danger small mb-1">{$form_data.mail.error}</div>
				<div class="input-group mb-3">
					{$form_data.mail.html}
					<div class="input-group-text"><i class="bi bi-envelope"></i></div>
				</div>
				<div class="d-flex justify-content-center gap-2">
					{$form_data.buttons.html}
				</div>
	{else}
				<p class="login-box-msg">{$login_box_msg}</p>
				<div class="text-danger small mb-1">{$form_data.username.error}</div>
				<div class="input-group mb-3">
					{$form_data.username.html}
					<div class="input-group-text"><i class="bi bi-person"></i></div>
				</div>
				<div class="text-danger small mb-1">{$form_data.password.error}</div>
				<div class="input-group mb-3">
					{$form_data.password.html}
					<div class="input-group-text"><i class="bi bi-lock-fill"></i></div>
				</div>
				<div class="d-flex justify-content-center align-items-center gap-3">
{if isset($form_data.autologin)}
					<div class="form-check mb-0">
						{$form_data.autologin.html}
					</div>
{/if}
					{$form_data.submit_button.html}
				</div>
{if isset($form_data.warning)}
				<div class="form-text text-muted small mt-1 text-center">{$form_data.warning.html}</div>
{/if}
	{/if}
	</form>
				<p class="mt-3 mb-1 text-center">{$form_data.recover_password.html}</p>
{/if}
			</div>
		</div>
		<p class="text-center text-muted small mt-3 mb-0">
			Copyright &copy; 2006-{php}echo date("Y"){/php} by Janusz Tylek & Karina Tylek
		</p>
		<p class="text-center mt-1 mb-0">
			<a href="http://epesibim.com/"><img src="images/epesi-powered.png" alt="EPESI powered" height="14"></a>
		</p>
	</div>
</div>
{/if}
