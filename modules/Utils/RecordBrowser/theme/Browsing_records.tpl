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
                <div class="button"><img id="roll" src="{$theme_dir}/Utils_RecordBrowser__roll-down.png" onClick="var x='{$theme_dir}/Utils_RecordBrowser__roll-';if(this.src.indexOf(x+'up.png')>=0)this.src=x+'down.png';else this.src=x+'up.png'; filters_roll();" width="14" height="14" alt="=" border="0"></div>
				<div style="display: block; float: right; padding-right: 10px; white-space: nowrap; padding: 4px;">Click to show / hide filters</div>
            </td>
        </tr>
        <tr>
            <td colspan="3"><div id="filters_box" style="display: none;">{if $filters}{$filters}{else}&nbsp;{/if}</div></td>
    	</tr>
	</tbody>
</table>
</div>

{$table}
