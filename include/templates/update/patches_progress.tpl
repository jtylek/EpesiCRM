<h4>{$heading}:</h4>
<p class="text-muted">{$last_refresh_label} - {$last_refresh}</p>
<table class="table table-sm table-striped align-middle">
	<thead>
		<tr><th>{$module_label}</th><th>{$patch_label}</th><th class="text-end">{$status_label}</th></tr>
	</thead>
	<tbody>
	{foreach from=$rows item=row}
		<tr>
			<td>{$row.module}</td>
			<td>{$row.description}</td>
			<td class="text-end">
				{if $row.timeout}
					<div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
				{else}
					<span class="badge text-bg-secondary">{$pending_label}</span>
				{/if}
				{if $row.user_message}
					<div class="small text-muted">{$row.user_message}</div>
				{/if}
			</td>
		</tr>
	{/foreach}
	</tbody>
</table>
<script type="text/javascript">location.reload(true)</script>
