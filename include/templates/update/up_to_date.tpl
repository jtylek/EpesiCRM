<p>{$message}</p>
{if $net_blocked}
<div class="alert alert-warning">{$net_blocked_msg}</div>
{/if}
{if $backups}
<h5 class="mt-4">{$backups_label}</h5>
<div role="table" style="display: grid; grid-template-columns: auto 1fr auto; align-items: center;">
	{foreach from=$backups item=b}
	<div role="row" style="display: contents;">
		<div class="border-bottom py-1 pe-2" role="cell">{$b.date}</div>
		<div class="border-bottom py-1 pe-2" role="cell">{$b.file}</div>
		<div class="border-bottom py-1 text-end" role="cell">
			<a class="btn btn-outline-secondary btn-sm" target="_blank" href="{$b.download_url}">{$download_label}</a>
			<a class="btn btn-outline-danger btn-sm" href="{$b.delete_url}">{$delete_label}</a>
		</div>
	</div>
	{/foreach}
</div>
{/if}
