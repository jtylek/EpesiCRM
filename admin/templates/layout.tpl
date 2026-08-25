<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="NOINDEX, NOARCHIVE" />
	<title>{if $title}{$title} - {/if}{'Epesi Admin Utilities'|t}</title>
	<link href="{$epesi_url}libs/bootstrap-5.3.8/css/bootstrap.min.css" rel="stylesheet" />
	<link href="{$epesi_url}libs/bootstrap-icons-1.13.1/bootstrap-icons.min.css" rel="stylesheet" />
	<link href="{$epesi_url}libs/adminlte-4.1.0/css/adminlte.min.css" rel="stylesheet" />
	{* Reused as-is (not copied) so this shell's sidebar/navbar chrome
	   (.epesi-adminlte/.app-header/.app-sidebar/.app-main/.app-content-header)
	   stays in sync with the real logged-in app's own AdminLTE shell -
	   adminltedark since the light-only adminlte theme was removed
	   (2026-08-04, see AI-shared/adminlte-theme.md); its default.css covers
	   light mode itself via [data-bs-theme="light"], matching the pin below. *}
	<link href="{$epesi_url}modules/Base/Box/theme_adminltedark/default.css" rel="stylesheet" />
	<link href="./images/admintools.css" rel="stylesheet" />
</head>
{* data-bs-theme pinned for the same reason as the login page - AdminLTE's JS
   otherwise follows the OS prefers-color-scheme and flips to its dark palette,
   which this page isn't styled for. *}
<body>
<div class="epesi-adminlte app-wrapper" data-bs-theme="light">

	<nav class="app-header navbar navbar-expand">
		<div class="container-fluid">
			<ul class="navbar-nav">
				<li class="nav-item">
					<a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="{'Toggle navigation'|t}">
						<i class="bi bi-list"></i>
					</a>
				</li>
				<li class="nav-item">
					<span class="nav-link"><i class="bi bi-tools me-1"></i>{'Admin Utilities'|t}</span>
				</li>
			</ul>
		</div>
	</nav>

	<aside class="app-sidebar shadow" id="admin_sidebar">
		<div class="sidebar-brand">
			<div class="brand-logo"><img src="./images/logo-small.png" alt="Epesi" /></div>
			<a class="sidebar-toggle-inline d-lg-none" data-lte-toggle="sidebar" href="#" role="button" aria-label="{'Toggle navigation'|t}">
				<i class="bi bi-list"></i>
			</a>
		</div>
		<div class="sidebar-wrapper">
			<nav class="mt-2">
				<ul class="nav sidebar-menu flex-column" role="navigation">
					{foreach from=$menu_entries item=entry}
					<li class="nav-item">
						<a href="{$entry.href}" class="nav-link{if $entry.key == $current_module} active{/if}">
							<i class="nav-icon bi {$entry.icon}"></i>
							<p>{$entry.text}</p>
						</a>
					</li>
					{/foreach}
				</ul>
			</nav>
		</div>
	</aside>

	<main class="app-main">
		<div class="app-content-header">
			<div class="container-fluid d-flex align-items-center justify-content-between flex-wrap gap-2">
				<h5 class="mb-0">{if $title}{$title}{else}{'Admin Utilities'|t}{/if}</h5>
				{if $show_action_links && $action_links}
				<div class="d-flex gap-2">
					{foreach from=$action_links item=link}
					<a href="{$link.href}" class="btn btn-outline-secondary btn-sm">{$link.text}</a>
					{/foreach}
				</div>
				{/if}
			</div>
		</div>
		<div class="app-content">
			<div class="container-fluid">
				{if $show_menu}
					{if $menu_entries}
					<div class="row g-3">
						{foreach from=$menu_entries item=entry}
						<div class="col-sm-6 col-lg-4">
							<a href="{$entry.href}" class="card text-decoration-none h-100 shadow-sm">
								<div class="card-body d-flex align-items-center gap-3">
									<i class="bi {$entry.icon} fs-2 text-secondary"></i>
									<span class="fw-semibold text-body">{$entry.text}</span>
								</div>
							</a>
						</div>
						{/foreach}
					</div>
					{else}
					<p class="text-muted">{'There is nothing here for you.'|t}</p>
					{/if}
				{else}
					<div class="card shadow-sm">
						<div class="card-body">
							{$body}
						</div>
					</div>
				{/if}
			</div>
		</div>
	</main>

	<footer class="app-footer text-center py-3">
		<div><a href="http://epesibim.com" target="_blank" rel="noopener"><img src="./images/epesi-powered.png" alt="EPESI powered" /></a></div>
		<div class="text-muted small">{'Copyright'|t} &copy; 2006-{$smarty.now|date_format:"%Y"} by Janusz Tylek and Karina Tylek</div>
		<div class="text-muted small">{'Support'|t}: <a href="https://epesi.org">https://epesi.org</a></div>
	</footer>

{* Module::wrap_confirm_js()/create_confirm_href() (include/module.php) expect this modal +
   window.epesi_confirm to exist - normally injected via eval_js_once() into the main app's
   own output, but this admin/ shell is a separate Smarty render that never flushes that
   queue, so it's included here directly instead, statically, same as this shell already does
   for Bootstrap/AdminLTE's own JS below. Keep this markup/script in sync with
   Module::inject_confirm_modal() if that ever changes - same technique, just delivered
   unconditionally instead of lazily since there's no shared session-JS-queue to lean on here. *}
<div class="modal fade" id="epesi_confirm_modal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered"><div class="modal-content">
		<div class="modal-header"><h5 class="modal-title"><i class="bi bi-question-circle-fill me-2 text-primary"></i>{'Confirm'|t}</h5>
		<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
		<div class="modal-body"><p class="mb-0" id="epesi_confirm_modal_msg" style="white-space:pre-line"></p></div>
		<div class="modal-footer">
			<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{'Cancel'|t}</button>
			<button type="button" class="btn btn-primary" id="epesi_confirm_modal_ok" autofocus>{'OK'|t}</button>
		</div>
	</div></div>
</div>
<script src="{$epesi_url}libs/bootstrap-5.3.8/js/bootstrap.bundle.min.js"></script>
<script src="{$epesi_url}libs/adminlte-4.1.0/js/adminlte.min.js"></script>
<script>
{literal}
window.epesi_confirm = window.epesi_confirm || function(msg, actionFn) {
	if (typeof bootstrap === 'undefined') { if (confirm(msg)) actionFn(); return; }
	var el = document.getElementById('epesi_confirm_modal');
	el.querySelector('#epesi_confirm_modal_msg').textContent = msg;
	var ok = el.querySelector('#epesi_confirm_modal_ok');
	var freshOk = ok.cloneNode(true);
	ok.parentNode.replaceChild(freshOk, ok);
	freshOk.addEventListener('click', function() {
		var m = bootstrap.Modal.getInstance(el);
		if (m) m.hide();
		actionFn();
	});
	bootstrap.Modal.getOrCreateInstance(el).show();
};
{/literal}
</script>
</body>
</html>
