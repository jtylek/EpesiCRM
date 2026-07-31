<div class="alert alert-danger">
	<strong>{'Could not connect to the database.'|t}</strong>
</div>

<table class="table table-sm mb-3">
	<tbody>
		<tr>
			<td>{'PHP MySQL driver (mysqli extension)'|t}</td>
			<td class="text-end">
				<span class="badge {if $mysqli_loaded}bg-success{else}bg-danger{/if}">{if $mysqli_loaded}{'Loaded'|t}{else}{'Not loaded'|t}{/if}</span>
			</td>
		</tr>
		<tr>
			<td>{'MySQL server reachable'|t} ({$diag_host}:{$diag_port})</td>
			<td class="text-end">
				<span class="badge {if $server_reachable}bg-success{else}bg-danger{/if}">{if $server_reachable}{'Reachable'|t}{else}{'Not reachable'|t}{/if}</span>
			</td>
		</tr>
	</tbody>
</table>

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
