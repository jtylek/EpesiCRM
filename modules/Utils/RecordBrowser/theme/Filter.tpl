<div id="Utils_RecordBrowser__Filter" style="text-align:left; width: 100%;">
	{php}
		eval_js_once('show_filters = 0');
//		eval_js('var b=document.getElementById(\'recordbrowser_filters_{$id}\');if(b){if(!show_filters){b.style.display=\'none\';document.getElementById(\'hide_filter_b\').style.display=\'none\';}else{document.getElementById(\'show_filter_b\').style.display=\'none\';}}');
	{/php}
	<div id="buttons">
		<input type="button" onClick="document.getElementById('recordbrowser_filters_{$id}').style.display='block';this.style.display='none';document.getElementById('hide_filter_b_{$id}').style.display='block';show_filters=1;" id="show_filter_b_{$id}" value="Show filters">
		<input type="button" style="display:none" onClick="document.getElementById('recordbrowser_filters_{$id}').style.display='none';this.style.display='none';document.getElementById('show_filter_b_{$id}').style.display='block';show_filters=0;" id="hide_filter_b_{$id}" value="Hide filters">
	</div>
</div>

<br>

{$form_open}
<div id="recordbrowser_filters_{$id}" style="display:none">
	{foreach item=f from=$filters}
		{$form_data.$f.label}&nbsp;{$form_data.$f.html}
	{/foreach}
	<div id="buttons" style="padding-top: 5px;">
		{$form_data.submit.html}
	</div>
</div>
{$form_close}
