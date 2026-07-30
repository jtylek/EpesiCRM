{* AdminLTE variant of FirstRun's page - see [[setup-check-adminlte-split]] memory
   for the sibling setup.php/check.php rewrite this matches visually. Unlike
   setuptheme's shell.tpl this isn't a full <html> document: FirstRun renders as
   an ordinary module inside the app's own theme/index.tpl shell (which already
   emits <html>/<head>/<body>), so this is a fragment only. *}
<div class="firstrun-shell">
	<div class="card shadow-sm firstrun-card">
		<div class="card-body">
			{$wizard}
		</div>
	</div>
	<div class="text-center firstrun-footer">
		<a href="https://epe.si" target="_blank" rel="noopener"><img src="images/epesi-powered.png" alt="EPESI powered" /></a>
		<div class="text-muted small mt-2">Copyright &copy; 2006-{php}echo date("Y"){/php} by Janusz Tylek</div>
	</div>
</div>
{php}
eval_js_once('document.body.id=\'FirstRun\'');
{/php}
