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
		<div style="display:flex; flex-direction:column;">
			{foreach item=e from=$elements_name}
				<div style="display:flex;">
					<div style="white-space:nowrap; flex:0 0 83px;">
						{$form_data.$e.label}
					</div>
					<div style="flex:1 1 auto; min-width:0;">
						{$form_data.$e.html}
					</div>
				</div>
			{/foreach}
		</div>
	{$form_close}
</div>