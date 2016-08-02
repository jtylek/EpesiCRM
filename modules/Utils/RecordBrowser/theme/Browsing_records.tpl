<div style="text-align: left;">
<table id="Browsing_records" class="nonselectable" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			{if isset($caption)}
				<td style="width:100px;">
					<div class="name">
						<img alt=" " class="icon" src="{$icon}" width="32" height="32" border="0">
						<div class="label">
							{if isset($form_data)}
								{$form_open}
							{/if}
							{$caption}
							{if isset($form_data)}
									{$form_data.browse_mode.html}
								{$form_close}
							{/if}
						</div>
					</div>
				</td>
				<td style="width:100%;">
				</td>
			{/if}
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
</div>
                

{$table}
