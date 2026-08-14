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
		<a href="http://epesibim.com" target="_blank" rel="noopener"><img src="images/epesi-powered.png" alt="EPESI powered" /></a>
		<div class="text-muted small mt-2">Copyright &copy; 2006-{php}echo date("Y"){/php} by Janusz Tylek and Karina Tylek</div>
	</div>
</div>
{php}
eval_js_once('document.body.id=\'FirstRun\'');
// Runs fresh on every step (unlike the once-only call above) since each
// wizard page swaps in different fields - without this, focus is left
// wherever the previous step's submit left it, so the caret appears to be
// floating over the wrong control.
eval_js('var el=document.querySelector(\'#FirstRun table#quickform input[type="text"], #FirstRun table#quickform input[type="password"], #FirstRun table#quickform select, #FirstRun table#quickform textarea\'); if(el) el.focus();');
{/php}
