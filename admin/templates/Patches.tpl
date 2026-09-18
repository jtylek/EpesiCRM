{if $mode == 'done'}
<h4>{'Epesi was patched and cache files were updated.'|t}</h4>
<div class="text-center"><a href="./index.php" class="btn btn-outline-secondary btn-sm">{'MAIN MENU'|t}</a></div>
{else}
	{if $mode == 'list'}
<h5>{'This utility scans for available patches and applies them as necessary'|t}</h5>

<div class="btn-group mb-3" role="group">
	<a href="?module=Patches&filter=uninstalled" class="btn btn-sm {if $filter == 'uninstalled'}btn-secondary{else}btn-outline-secondary{/if}">{'Uninstalled'|t}</a>
	<a href="?module=Patches&filter=installed" class="btn btn-sm {if $filter == 'installed'}btn-secondary{else}btn-outline-secondary{/if}">{'Installed'|t}</a>
</div>
	{/if}

<div class="table-responsive">
<table class="table table-sm table-hover align-middle mb-3">
	<thead>
		<tr>
			<th>{'Module'|t}</th>
			<th>{'Patch'|t}</th>
			<th class="text-nowrap">{'Applied'|t}</th>
			<th class="text-end">{'Status'|t}</th>
		</tr>
	</thead>
	<tbody>
	{foreach from=$rows item=row}
		<tr>
			<td class="text-nowrap">{$row.module}</td>
			<td>
				<div{if $row.strong} class="fw-semibold"{/if}>{$row.description}</div>
				<div class="text-muted small font-monospace text-truncate" style="max-width: 480px;" title="{$row.name}">{$row.name}</div>
				{if $row.extra}<pre class="text-muted small mb-0 mt-1">{$row.extra|escape}</pre>{/if}
			</td>
			<td class="text-nowrap text-muted small">{if $row.date}{$row.date}{else}&mdash;{/if}</td>
			<td class="text-end"><span class="badge {$row.badge_class}">{$row.status_text}</span></td>
		</tr>
	{/foreach}
	</tbody>
</table>
</div>

<div class="d-flex gap-3 mb-3">
	{if $new_count}<span>{'New patches found:'|t} <strong class="text-danger">{$new_count}</strong></span>{/if}
	{if $installed_count}<span>{'Patches already installed:'|t} <strong class="text-success">{$installed_count}</strong></span>{/if}
	{if $patched_success}<span>{'Patches successfully installed:'|t} <strong class="text-success">{$patched_success}</strong></span>{/if}
	{if $patched_failure}<span>{'Patches with errors:'|t} <strong class="text-danger">{$patched_failure}</strong></span>{/if}
	{if $patches_to_run}<span>{'Patches to run:'|t} <strong class="text-secondary">{$patches_to_run}</strong></span>{/if}
</div>

<p class="fw-semibold text-center">{$message}</p>
{/if}
