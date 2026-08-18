<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="NOINDEX, NOARCHIVE" />
	<title>{if $title}{$title} - {/if}{'Epesi setup wizard'|t}</title>
	<link href="libs/bootstrap-5.3.8/css/bootstrap.min.css" rel="stylesheet" />
	<link href="libs/bootstrap-icons-1.13.1/bootstrap-icons.min.css" rel="stylesheet" />
	<link href="libs/adminlte-4.1.0/css/adminlte.min.css" rel="stylesheet" />
	<style>
	{literal}
	/* Same reasoning as update_shell.tpl/login_page.tpl: give body/header an
	   explicit background so a dark-mode browser doesn't substitute its own
	   auto-dark page background around the still-white .card. */
	body {
		background-color: #f4f6f9;
	}
	.app-header {
		background-color: #fff;
		border-bottom: 1px solid #dee2e6;
	}
	.setup-card {
		max-width: 960px;
		margin: 0 auto;
	}
	.setup-steps {
		max-width: 960px;
		margin: 0 auto 1rem;
	}
	.setup-steps .step {
		flex: 1;
		text-align: center;
		font-size: 0.8rem;
		color: #adb5bd;
		border-top: 3px solid #dee2e6;
		padding-top: 0.5rem;
	}
	.setup-steps .step .bi {
		display: inline-block;
		width: 1.6em;
		height: 1.6em;
		line-height: 1.6em;
		border-radius: 50%;
	}
	/* Done steps are deliberately muted (gray, not green) - a saturated
	   success-green checkmark reads as "active/go" at a glance and, with
	   three of them in a row, visually outweighs the single current-step
	   badge even when that one is blue. Only .current gets a strong color,
	   so there's exactly one loud element in the bar: the step you're on. */
	.setup-steps .step.done {
		color: #6c757d;
		border-top-color: #6c757d;
	}
	.setup-steps .step.done .bi {
		background-color: #6c757d;
		color: #fff;
	}
	.setup-steps .step.current {
		color: #0d6efd;
		font-weight: 600;
		border-top-color: #0d6efd;
	}
	.setup-steps .step.current .bi {
		background-color: #0d6efd;
		color: #fff;
		box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.2);
	}
	.setup-license-text {
		max-height: 260px;
		overflow-y: auto;
		background-color: #fff;
		border: 1px solid #dee2e6;
		border-radius: 0.375rem;
		padding: 1rem;
	}
	/* Bootstrap's heading sizes are rem-based (root <html> size), so they
	   don't shrink from .setup-card's own font-size alone - same reason
	   .form-control/.btn need their own overrides above. */
	.setup-license-text h3 {
		font-size: 1.1rem;
		margin-bottom: 0.5rem;
	}
	.setup-license-text h4 {
		font-size: 0.95rem;
		margin-bottom: 0.75rem;
	}
	/* Smaller text/controls throughout the setup wizard, per request - most
	   Bootstrap components (.form-control/.form-select/.btn) size themselves
	   off rem (root <html> size), not the nearest ancestor's font-size, so
	   plain inheritance from .setup-card alone wouldn't shrink them; this
	   page is a standalone document used only by setup.php/check.php, so
	   these overrides can't leak into the main app. */
	.setup-card {
		font-size: 0.875rem;
	}
	.setup-card .card-header h5 {
		font-size: 1rem;
	}
	.setup-card h6 {
		font-size: 0.9rem;
	}
	.setup-card .form-control,
	.setup-card .form-select {
		font-size: 0.85rem;
		padding: 0.3rem 0.6rem;
	}
	.setup-card .btn {
		font-size: 0.85rem;
		padding: 0.3rem 0.9rem;
	}
	.setup-card .form-check-label,
	.setup-card .col-form-label {
		font-size: 0.85rem;
	}
	.setup-card .col-form-label {
		padding-top: 0.3rem;
		padding-bottom: 0.3rem;
	}
	@media (max-width: 767.98px) {
		.setup-card, .setup-steps {
			max-width: 100%;
		}
	}
	{/literal}
	</style>
</head>
<body>
<div class="epesi-adminlte app-wrapper" data-bs-theme="light">

	<nav class="app-header navbar navbar-expand">
		<div class="container-fluid justify-content-center">
			<span class="navbar-brand"><i class="bi bi-gear-fill me-1"></i>{'Epesi setup wizard'|t}</span>
		</div>
	</nav>

	<main class="app-main">
		<div class="app-content">
			<div class="container-fluid">
{if $steps}
				<div class="setup-steps d-flex gap-2 mt-3">
{foreach from=$steps item=step}
					<div class="step{if $step.done} done{elseif $step.current} current{/if}">
						<i class="bi {if $step.done}bi-check-circle-fill{else}{$step.icon}{/if}"></i>
						<div>{$step.label}</div>
					</div>
{/foreach}
				</div>
{/if}
				<div class="card shadow-sm setup-card">
{if $title}
					<div class="card-header">
						<h5 class="mb-0">{$title}</h5>
					</div>
{/if}
					<div class="card-body">
						{$body}
					</div>
				</div>
			</div>
		</div>
	</main>

	<footer class="app-footer text-center py-3">
		<div><a href="http://epesibim.com" target="_blank" rel="noopener"><img src="images/epesi-powered.png" alt="EPESI powered" /></a></div>
		<div class="text-muted small">{'Copyright'|t} &copy; 2006-{$smarty.now|date_format:"%Y"} by Janusz and Karina Tylek</div>
	</footer>

</div>
<script src="libs/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script src="libs/adminlte-4.1.0/js/adminlte.min.js"></script>
</body>
</html>
