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
                    <div class="filters_button" onClick="filters_roll();">
                        <div id="filters_button_icon"></div>
                        <div id="filters_button_text">Show filters</span>
                    </div>
                {else}
                    &nbsp;
                {/if}
            </td>
        </tr>
        <tr>
            <td colspan="3" class="filters">
                <div id="filters_box" style="display: none;">
                    <div id="filters" style="display: none;">{if $filters}{$filters}{else}&nbsp;{/if}</div>
                </div>
            </td>
    	</tr>
	</tbody>
</table>
</div>

{$table}
