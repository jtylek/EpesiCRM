<div class="alert alert-danger">
	<strong>{'Could not connect to the database.'|t}</strong>
</div>

<div class="list-group list-group-flush mb-3">
	<div class="list-group-item d-flex justify-content-between align-items-center">
		{'PHP MySQL driver (mysqli extension)'|t}
		<span class="badge {if $mysqli_loaded}bg-success{else}bg-danger{/if}">{if $mysqli_loaded}{'Loaded'|t}{else}{'Not loaded'|t}{/if}</span>
	</div>
	<div class="list-group-item d-flex justify-content-between align-items-center">
		{'MySQL server reachable'|t} ({$diag_host}:{$diag_port})
		<span class="badge {if $server_reachable}bg-success{else}bg-danger{/if}">{if $server_reachable}{'Reachable'|t}{else}{'Not reachable'|t}{/if}</span>
	</div>
</div>

{if $driver_error}
<div class="mb-3">
	<div class="small text-muted mb-1">{'Driver message:'|t}</div>
	<div class="alert alert-secondary small mb-0">{$driver_error}</div>
</div>
{/if}

<p class="small text-muted">
{if !$mysqli_loaded}
{'Enable the mysqli extension in php.ini and restart your web server, then try again.'|t}
{elseif !$server_reachable}
{'The MySQL/MariaDB server does not appear to be running, or is not reachable at the address/port above - check that the service is started and that no firewall is blocking the connection.'|t}
{else}
{'The server is reachable and the driver is available - double-check the username, password and database name.'|t}
{/if}
</p>

<div class="text-center mt-3">
	<a class="btn btn-primary" href="{$retry_url}">{'Try again'|t}</a>
</div>
