<table id="Browsing_records" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			{if $select_form != ""}
			<td width="200px">
				{$select_form}
			</td>
			{/if}
			<td>
				<div id="Utils_RecordBrowser__Filter">
					<div class="buttons" style="float:left;">
					{if $select_all.js!=""}
						<input type="button" onClick="{$select_all.js}" value="{$select_all.label}">
				    {/if}
					{if $deselect_all.js!=""}
						<input type="button" onClick="{$deselect_all.js}" value="{$deselect_all.label}">
					{/if}
					{if isset($close_leightbox)}
						<input type="button" onClick="{$close_leightbox.js}" value="{$close_leightbox.label}">
					{/if}
					</div>
				</div>
			</td>
			<td>
			</td>
			<td class="filters">
                {if $filters.controls}
	                {$filters.controls}
                {/if}
			</td>
		</tr>
		{if $filters.elements}
        <tr>
            <td colspan="3" class="filters">
            {$filters.elements}
            </td>
        </tr>
        {/if}
	</tbody>
</table>
{$table}