<div class="epesi-cron">
	<div class="card mb-3">
		<div class="card-header d-flex align-items-center gap-2">
			<i class="bi bi-clock fs-4 text-secondary"></i>
			<h5 class="mb-0">{'Cron'|t}</h5>
		</div>
		<div class="card-body">
			<p>{'Cron periodically executes scheduled jobs for every module. It needs to run at least once every minute, using one of the two methods below.'|t}</p>

			<ol class="list-group list-group-numbered mb-3">
				<li class="list-group-item d-flex align-items-start gap-2">
					<i class="bi bi-terminal fs-5 text-secondary mt-1"></i>
					<span>{'Recommended: have your server run cron.php from the command line every minute, e.g. via a system cron job.'|t}</span>
				</li>
				<li class="list-group-item d-flex align-items-start gap-2">
					<i class="bi bi-globe2 fs-5 text-secondary mt-1"></i>
					<span>{"If you can't run PHP from the command line, use the Cron URL below instead. Loading it - in a browser, or via an external service that fetches URLs on a schedule - triggers the same cron run over HTTP."|t}</span>
				</li>
			</ol>

			<p class="text-body-secondary mb-3">
				<i class="bi bi-book"></i>
				{'You can read more on our wiki'|t}: <a href="{$wiki_url}" target="_blank">{$wiki_url}</a>
			</p>

			<div class="mb-3">
				<div class="fw-semibold mb-1"><i class="bi bi-link-45deg"></i> {'Cron URL'|t}</div>
				<a href="{$cron_url}" target="_blank" class="epesi-cron-url d-inline-block font-monospace text-break">{$cron_url}</a>
			</div>

			<div class="alert alert-warning d-flex align-items-start gap-2 mb-3" role="alert">
				<i class="bi bi-exclamation-triangle-fill mt-1"></i>
				<div>{'This URL contains a secret token, so anyone with it can trigger cron. Keep it private, and generate a new one if you suspect it has leaked.'|t}</div>
			</div>

			<a {$new_token_href} class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat"></i> {'New Token'|t}</a>
		</div>
	</div>
</div>

<div>{$history}</div>
