	
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
					<div name="events_brief" class=events_brief id="{$event.div_id}">
						<span class="event_drag_handle">{$event.move}</span>
						<span id="{$event.div_id}_brief">{$event.brief}</span>
						<span>{$event.more}</span>
					</div>
					{/foreach}
				{/if}
				</td>
			{/foreach}
			</tr>
			
		{/foreach}
	</table>
