<h4>{$heading}:</h4>
<p class="text-muted">{$last_refresh_label} - {$last_refresh}</p>
<div role="table" style="display: grid; grid-template-columns: auto 1fr auto; align-items: center;">
	<div role="row" style="display: contents;">
		<div class="fw-semibold border-bottom py-1 pe-2" role="columnheader">{$module_label}</div>
		<div class="fw-semibold border-bottom py-1 pe-2" role="columnheader">{$patch_label}</div>
		<div class="fw-semibold border-bottom py-1 text-end" role="columnheader">{$status_label}</div>
	</div>
	{foreach from=$rows item=row}
	<div role="row" style="display: contents;">
		<div class="border-bottom py-1 pe-2" role="cell">{$row.module}</div>
		<div class="border-bottom py-1 pe-2" role="cell">{$row.description}</div>
		<div class="border-bottom py-1 text-end" role="cell">
			{if $row.timeout}
				<div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
			{else}
				<span class="badge text-bg-secondary">{$pending_label}</span>
			{/if}
			{if $row.user_message}
				<div class="small text-muted">{$row.user_message}</div>
			{/if}
		</div>
	</div>
	{/foreach}
</div>
<script type="text/javascript">location.reload(true)</script>
