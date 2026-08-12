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

<div role="table" style="display: grid; grid-template-columns: auto 1fr auto; align-items: center;">
	{foreach from=$rows item=row}
	<div role="row" style="display: contents;">
		<div class="border-bottom py-1 pe-2{if $row.strong} fw-semibold{/if}" role="cell">{$row.module}</div>
		<div class="border-bottom py-1 pe-2{if $row.strong} fw-semibold{/if}" role="cell">{$row.description}</div>
		<div class="border-bottom py-1 text-end" role="cell"><span class="badge {$row.badge_class}">{$row.status_text}</span></div>
	</div>
	{if $row.extra}
	<div role="row" style="display: contents;">
		<div class="border-bottom py-1" role="cell" style="grid-column: 1 / -1;"><pre class="text-muted small mb-0">{$row.extra|escape}</pre></div>
	</div>
	{/if}
	{/foreach}
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
