<table id="Browsing_records" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td>
				<div id="Utils_RecordBrowser__Filter">
					<div class="buttons" style="float:left;">
					{if $select_all.js!=""}
						<input type="button" onClick="{$select_all.js}" value="{$select_all.label}">
				    {/if}
					{if $deselect_all.js!=""}
						<input type="button" onClick="{$deselect_all.js}" value="{$deselect_all.label}">
					{/if}
						<input type="button" onClick="{$close_leightbox.js}" value="{$close_leightbox.label}">
					</div>
				</div>
			</td>
			<td class="filters">
				{$filters}
			</td>
{$table}