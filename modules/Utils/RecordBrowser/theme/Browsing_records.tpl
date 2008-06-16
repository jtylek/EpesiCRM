{php}
	load_js('data/Base_Theme/templates/default/Utils_RecordBrowser__Browsing_records.js');
{/php}

<div style="text-align: left; padding-bottom: 3px;">
<table id="Browsing_records" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td class="icon"><img src="{$icon}" width="32" height="32" border="0"></td>
			<td class="name">{$caption}</td>
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
