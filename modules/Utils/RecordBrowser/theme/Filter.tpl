<div id="Utils_RecordBrowser__Filter">
{*
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
		<input type="button" {if isset($dont_hide)}style="display: none;"{/if} onClick="document.getElementById('recordbrowser_filters_{$id}').style.display='block';this.style.display='none';document.getElementById('hide_filter_b_{$id}').style.display='block';show_filters=1;" id="show_filter_b_{$id}" value="Show filters">
		<input type="button" {if !isset($dont_hide)}style="display: none;"{/if} onClick="document.getElementById('recordbrowser_filters_{$id}').style.display='none';this.style.display='none';document.getElementById('show_filter_b_{$id}').style.display='block';show_filters=0;" id="hide_filter_b_{$id}" value="Hide filters">
	</div>
</div>
*}
{$form_open}
{*<div id="recordbrowser_filters_{$id}" class="Utils_RecordBrowser__Filter" {if !isset($dont_hide)}style="display: none;"{/if}>*}
<div id="recordbrowser_filters_{$id}" class="Utils_RecordBrowser__Filter">
	<table border="0" cellpadding="0" cellspacing="0">
		<tr>
			{foreach item=f from=$filters}
				<td class="label">{$form_data.$f.label}</td>
				<td class="data">{$form_data.$f.html}</td>
			{/foreach}
			<td class="buttons">{$form_data.submit.html}</td>
		</tr>
	</table>
</div>
{$form_close}
