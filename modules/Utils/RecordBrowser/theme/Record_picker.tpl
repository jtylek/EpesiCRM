<table id="Browsing_records" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td>
				<div id="Utils_RecordBrowser__Filter">
					<div class="buttons{$disabled}" style="float:left;">
						<input type="button" onClick="{$select_all.js}" value="{$select_all.label}">
						<input type="button" onClick="{$deselect_all.js}" value="{$deselect_all.label}">
					</div>
				</div>
			</td>
			<td class="filters">
				{$filters}
			</td>
{$table}