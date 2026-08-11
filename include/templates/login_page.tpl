<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="NOINDEX, NOARCHIVE" />
	<title>{$title}</title>
	<link href="libs/bootstrap-5.3.8/css/bootstrap.min.css" rel="stylesheet" />
	<link href="libs/bootstrap-icons-1.13.1/bootstrap-icons.min.css" rel="stylesheet" />
	<link href="libs/adminlte-4.1.0/css/adminlte.min.css" rel="stylesheet" />
	{* Reused as-is (not copied) so this page's login-box/card chrome stays in
	   sync with the real app's own login screen - adminltedark since the
	   light-only adminlte theme was removed (2026-08-04, see
	   AI-shared/adminlte-theme.md); its default.css covers light mode itself
	   via [data-bs-theme="light"] - see that file's own comment for why
	   data-bs-theme is pinned to light below. *}
	<link href="modules/Base/User/Login/theme_adminltedark/default.css" rel="stylesheet" />
</head>
<body>
<div class="login-page-adminlte d-flex align-items-center justify-content-center" data-bs-theme="light">
	<div class="login-box">
		<div class="card">
			<div class="card-body login-card-body">
				<div class="login-logo">
					<img border="0" src="modules/Base/Theme/images/logo.png" width="550" height="200" alt="EPESI">
				</div>
{if $message}
				<p class="login-box-msg">{$message}</p>
{else}
				{$form_data.javascript}
				<p class="login-box-msg">{$login_box_msg}</p>
				<form {$form_data.attributes}>
				{$form_data.hidden}
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
				<div class="d-flex justify-content-center">
					{$form_data.submit_button.html}
				</div>
				</form>
{/if}
			</div>
		</div>
		<p class="text-center mt-1 mb-0">
			<a href="http://epesibim.com" target="_blank" rel="noopener"><img src="images/epesi-powered.png" alt="EPESI powered"></a>
		</p>
	</div>
</div>
<script src="libs/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script src="libs/adminlte-4.1.0/js/adminlte.min.js"></script>
</body>
</html>
