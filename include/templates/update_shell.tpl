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
				<div class="card shadow-sm">
					<div class="card-body">
						{$body}
					</div>
				</div>
			</div>
		</div>
	</main>

	<footer class="app-footer text-center py-3">
		<div><a href="http://www.epesi.org" target="_blank" rel="noopener"><img src="images/epesi-powered.png" alt="EPESI powered" /></a></div>
		<div class="text-muted small">Copyright &copy; 2006 - {$smarty.now|date_format:"%Y"} &bull; <a href="http://www.telaxus.com">Janusz Tylek</a></div>
	</footer>

</div>
<script src="libs/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script src="libs/adminlte-4.1.0/js/adminlte.min.js"></script>
</body>
</html>
