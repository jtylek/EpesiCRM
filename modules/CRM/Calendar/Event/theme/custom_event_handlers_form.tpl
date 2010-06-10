{php}
	eval_js('var_hide_calendar_event_handlers_popup=1');
{/php}

<div class="button" id="calendar_event_handlers_trigger" onmouseover="var_hide_calendar_event_handlers_popup=0;$('calendar_event_handlers_popup').style.display='';" onmouseout="var_hide_calendar_event_handlers_popup=1;setTimeout('hide_calendar_event_handlers_popup();',1000);">
	{$label}
</div>
<div onmouseover="$('calendar_event_handlers_popup').style.display='';var_hide_calendar_event_handlers_popup=0;" onmouseout="var_hide_calendar_event_handlers_popup=1;setTimeout('hide_calendar_event_handlers_popup();',400);" id="calendar_event_handlers_popup" style="display:none;position:absolute;z-index:2001;border:1px solid;background-color:#DDDDDD;right:5px;">
	{$form_open}
		<table>
			{foreach item=e from=$elements_name}
				<tr>
					<td nowrap="1">
						{$form_data.$e.label}
					</td>
					<td>
						{$form_data.$e.html}
					</td>
				</tr>
			{/foreach}
		</table>
	{$form_close}
</div>