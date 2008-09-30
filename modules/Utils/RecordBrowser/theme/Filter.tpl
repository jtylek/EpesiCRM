<div id="Utils_RecordBrowser__Filter">
{if isset($dont_hide)}
	{php}
		eval_js_once('show_filters = 1');
	{/php}
{else}
	{php}
		eval_js_once('show_filters = 0');
	{/php}
{/if}
	<div class="buttons">
		<input type="button" {if isset($dont_hide)}style="display: none;"{/if} onClick="document.getElementById('recordbrowser_filters_{$id}').style.display='block';this.style.display='none';document.getElementById('hide_filter_b_{$id}').style.display='block';show_filters=1;" id="show_filter_b_{$id}" value="{$show_filters}">
		<input type="button" {if !isset($dont_hide)}style="display: none;"{/if} onClick="document.getElementById('recordbrowser_filters_{$id}').style.display='none';this.style.display='none';document.getElementById('show_filter_b_{$id}').style.display='block';show_filters=0;" id="hide_filter_b_{$id}" value="{$hide_filters}">
	</div>
</div>

            </td>
        </tr>
        <tr>
            <td colspan="3" class="filters">

{$form_open}

<div id="recordbrowser_filters_{$id}" class="Utils_RecordBrowser__Filter" {if !isset($dont_hide)}style="display: none;"{/if}>
	<table border="0" cellpadding="0" cellspacing="0" style="margin-right:0;margin-left:auto;">
		<tr>
			{assign var=x value=0}
			{assign var=first value=1}
			{foreach item=f from=$filters}
				{if $x==4}
					{if $first==1}
						<td class="buttons">{$form_data.submit.html}</td>
						{assign var=first value=0}
					{else}
						<td />
					{/if}
					{assign var=x value=0}
					</tr>
					<tr>
				{/if}
				<td class="label">{$form_data.$f.label}</td>
				<td class="data" style="width:100px;">{$form_data.$f.html}</td>
				{assign var=x value=$x+1}
			{/foreach}
			{if $first==1}
				<td class="buttons">{$form_data.submit.html}</td>
			{/if}
		</tr>
	</table>
</div>

{$form_close}
            </td>
    	</tr>
	</tbody>
</table>
</div>
