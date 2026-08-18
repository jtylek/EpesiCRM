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
	<style>
	{literal}
	/* Neither this shell nor its data-bs-theme="light" wrapper below set an
	   explicit background, so a dark-mode browser was free to substitute its
	   own auto-dark page background (pure black) around the still-white
	   .card - login_page.tpl/admin/layout.tpl avoid this the same way, by
	   giving body/the header an explicit color instead of leaving it unset. */
	body {
		background-color: #f4f6f9;
	}
	.app-header {
		background-color: #fff;
		border-bottom: 1px solid #dee2e6;
	}
	.update-card {
		max-width: 50%;
		margin: 0 auto;
	}
	@media (max-width: 767.98px) {
		.update-card {
			max-width: 100%;
		}
	}
	{/literal}
	</style>
</head>
<body>
<div class="epesi-adminlte app-wrapper" data-bs-theme="light">

	<nav class="app-header navbar navbar-expand">
		<div class="container-fluid">
			<span class="navbar-brand"><i class="bi bi-cloud-arrow-down me-1"></i>{$title}</span>
		</div>
	</nav>

	<main class="app-main">
		<div class="app-content">
			<div class="container-fluid">
				<div class="card shadow-sm update-card">
					<div class="card-body">
						{$body}
					</div>
				</div>
			</div>
		</div>
	</main>

	<footer class="app-footer text-center py-3">
		<div><a href="http://epesibim.com" target="_blank" rel="noopener"><img src="images/epesi-powered.png" alt="EPESI powered" /></a></div>
		<div class="text-muted small">Copyright &copy; 2006 - {$smarty.now|date_format:"%Y"} by Janusz and Karina Tylek</div>
	</footer>

</div>
<script src="libs/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script src="libs/adminlte-4.1.0/js/adminlte.min.js"></script>
</body>
</html>
