<div style="text-align: left;">
<table id="Browsing_records" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			{if isset($caption)}
				<td class="icon"><img src="{$icon}" width="32" height="32" border="0"></td>
				<td class="name">
					{if isset($form_data)}
						{$form_open}
							{$form_data.browse_mode.html}
					{/if}
					{$caption}
					{if isset($form_data)}
						{$form_close}
					{/if}
				</td>
			{/if}
    		<td class="filters">
                {if $filters}
	                {$filters}
                {else}
            </td>
        </tr>
	</tbody>
</table>
</div>
                {/if}

{$table}
