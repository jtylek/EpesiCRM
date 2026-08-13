<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>{$header}</title>
	<link href="{$url}/libs/bootstrap-5.3.8/css/bootstrap.min.css" rel="stylesheet" />
	<link href="{$url}/libs/bootstrap-icons-1.13.1/bootstrap-icons.min.css" rel="stylesheet" />
	<link href="{$url}/libs/adminlte-4.1.0/css/adminlte.min.css" rel="stylesheet" />
	<style>
	{literal}
	/* Same reasoning as setuptheme/shell.tpl - give body/header an explicit
	   background so a dark-mode browser doesn't substitute its own auto-dark
	   page background around the still-white .card. This is a standalone
	   document (Utils_FrontPageCommon::display(), used by the TOS/Privacy
	   pages and any other bare content page), not part of the main app
	   shell, so these rules can't leak anywhere else. */
	body {
		background-color: #f4f6f9;
	}
	.app-header {
		background-color: #fff;
		border-bottom: 1px solid #dee2e6;
	}
	.frontpage-card {
		max-width: 960px;
	}
	{/literal}
	</style>
</head>
<body>
<div class="epesi-adminlte app-wrapper" data-bs-theme="light">

	<nav class="app-header navbar navbar-expand">
		<div class="container-fluid">
			<a class="navbar-brand d-flex align-items-center" href="{$url}">
				<img src="{$url}/{$logo}" height="32" class="me-2" alt="" />
				EPESI
			</a>
		</div>
	</nav>

	<main class="app-main">
		<div class="app-content">
			<div class="container-fluid py-4">
				<div class="row justify-content-center">
					<div class="col-lg-9 frontpage-card">
						<div class="card shadow-sm">
{if $header}
							<div class="card-header">
								<h5 class="mb-0">{$header}</h5>
							</div>
{/if}
							<div class="card-body">
								<div class="row">
									<div class="{if $info}col-md-8{else}col-12{/if}">
										{$contents}
									</div>
{if $info}
									<div class="col-md-4">
										{$info}
									</div>
{/if}
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</main>

	<footer class="app-footer text-center py-3">
{if $footer}
		<div>{$footer}</div>
{/if}
		<div><a href="http://epesibim.com" target="_blank" rel="noopener"><img src="{$url}/images/epesi-powered.png" alt="EPESI powered" /></a></div>
	</footer>

</div>
<script src="{$url}/libs/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
</body>
</html>
