{php}
	eval_js('var_hide_calendar_event_handlers_popup=1;');
{/php}
{literal}
<div class="button" style="width:100%;" id="calendar_event_handlers_trigger" onmouseover="var_hide_calendar_event_handlers_popup=0;show_calendar_event_handlers_popup();" onmouseout="var_hide_calendar_event_handlers_popup=1;setTimeout('hide_calendar_event_handlers_popup();',1000);" onclick="if(var_hide_calendar_event_handlers_popup==0){var_hide_calendar_event_handlers_popup=1;hide_calendar_event_handlers_popup();}else{var_hide_calendar_event_handlers_popup=0;show_calendar_event_handlers_popup();}">
{/literal}
	{$label}
</div>
<div onmouseover="show_calendar_event_handlers_popup();var_hide_calendar_event_handlers_popup=0;" onmouseout="var_hide_calendar_event_handlers_popup=1;setTimeout('hide_calendar_event_handlers_popup();',400);" id="calendar_event_handlers_popup" style="display:none;position:absolute;z-index:2001;border:1px solid;background-color:#DDDDDD;width:115px;">
	{$form_open}
		<table>
			{foreach item=e from=$elements_name}
				<tr>
					<td nowrap="1" style="width:83px;">
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