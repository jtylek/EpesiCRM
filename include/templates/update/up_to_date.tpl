<p>{$message}</p>
{if $net_blocked}
<div class="alert alert-warning">{$net_blocked_msg}</div>
{/if}
{if $backups}
<h5 class="mt-4">{$backups_label}</h5>
<table class="table table-sm table-striped align-middle">
	<tbody>
	{foreach from=$backups item=b}
		<tr>
			<td>{$b.date}</td>
			<td>{$b.file}</td>
			<td class="text-end">
				<a class="btn btn-outline-secondary btn-sm" target="_blank" href="{$b.download_url}">{$download_label}</a>
				<a class="btn btn-outline-danger btn-sm" href="{$b.delete_url}">{$delete_label}</a>
			</td>
		</tr>
	{/foreach}
	</tbody>
</table>
{/if}
