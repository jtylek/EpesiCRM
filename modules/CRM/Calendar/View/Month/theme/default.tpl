	
	<table name=CRMCalendar class=big>
		<tr>
		{section name=header loop=$header}
			<td class={$header[header].class}>{$header[header].cnt}</td>
		{/section}
		</tr>
		{foreach item=days from=$weeks}
			
	      	<tr>
			{foreach item=day from=$days}
				{if $day.class == 'today'}
					<td class=today>{$day.info}
				{elseif $day.class == 'week_number'}
					<td class=header>{$day.info}
				{else}
					<td class=day>{$day.info}
				{/if}
				{if $day.event_num > 0}
					{foreach item=event from=$day.event}
					<div name="events_brief" class=events_brief id="{$event.div_id}_b" onmouseout="mini_calendar_month_hideDetails('{$event.div_id}');" onmouseover="mini_calendar_month_showDetails('{$event.div_id}');">
						<div class=events id="{$event.div_id}_f" onmouseout="mini_calendar_month_hideDetails('{$event.div_id}');" onmouseover="mini_calendar_month_showDetails('{$event.div_id}');"><table class=x_details><tr><td>{$event.full}</td></tr></table></div>
						{$event.brief}
					</div>
					{/foreach}
				{/if}
				</td>
			{/foreach}
			</tr>
			
		{/foreach}
	</table>
