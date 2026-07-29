{if $mode == 'done'}
<h4>{'Epesi was patched and cache files were updated.'|t}</h4>
<div class="text-center"><a href="./index.php" class="btn btn-outline-secondary btn-sm">{'MAIN MENU'|t}</a></div>
{else}
	{if $mode == 'list'}
<h5>{'This utility scans for available patches and applies them as necessary'|t}</h5>
	{/if}

<table class="table table-sm table-striped align-middle">
	<tbody>
		{foreach from=$rows item=row}
		<tr>
			<td{if $row.strong} class="fw-semibold"{/if}>{$row.module}</td>
			<td{if $row.strong} class="fw-semibold"{/if}>{$row.description}</td>
			<td class="text-end"><span class="badge {$row.badge_class}">{$row.status_text}</span></td>
		</tr>
		{if $row.extra}
		<tr>
			<td colspan="3"><pre class="text-muted small mb-0">{$row.extra|escape}</pre></td>
		</tr>
		{/if}
		{/foreach}
	</tbody>
</table>

<div class="d-flex gap-3 mb-3">
	{if $new_count}<span>{'New patches found:'|t} <strong class="text-danger">{$new_count}</strong></span>{/if}
	{if $installed_count}<span>{'Patches already installed:'|t} <strong class="text-success">{$installed_count}</strong></span>{/if}
	{if $patched_success}<span>{'Patches successfully installed:'|t} <strong class="text-success">{$patched_success}</strong></span>{/if}
	{if $patched_failure}<span>{'Patches with errors:'|t} <strong class="text-danger">{$patched_failure}</strong></span>{/if}
	{if $patches_to_run}<span>{'Patches to run:'|t} <strong class="text-secondary">{$patches_to_run}</strong></span>{/if}
</div>

<p class="fw-semibold text-center">{$message}</p>
{/if}
