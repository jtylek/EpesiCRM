	<table name=CRMCalendar cellspacing=0 class=week>
		<tr>
{* shows month *}
			<td class=hours_header>&nbsp;</td> 
			
			{section name=header_month loop=$header_month}
					<td class=header_month colspan={$header_month[header_month].colspan}>{$header_month[header_month].info}</td>
			{/section}
		</tr>
		
		<tr>
{* this row contains days of month *}
			<td class=hours_header>&nbsp;</td>
			
			{section name=header_day loop=$header_day}
				{if $header_day[header_day].class == 'today'}
					<td class=header_day_today>{$header_day[header_day].info}</td>
				{else}
					<td class=header_day>{$header_day[header_day].info}</td>
				{/if}
			{/section}
		</tr>
		
		<tr>
{* this row contains timeless events *}
			<td class=hours_header_lower>&nbsp;</td>
			
			{foreach item=t_event from=$timeless_event}
				<td class=header_timeless id={$t_event.id}>
				{if $t_event.event_num > 0}
					{foreach item=event from=$t_event.event}
					<div name="events_brief" class=events_brief id="{$event.div_id}">
						<span class="event_drag_handle">X</span>
						{$event.brief}
					</div>
					{/foreach}
				{else}
					&nbsp;
				{/if}
				</td>
			{/foreach}
		</tr>
		
		<tr>
		{* timetable *}
		{section name=tt loop=$tt}
			
			{* $col is the current column *}
	      	{math assign="col" equation="x-1" x=$smarty.section.tt.rownum}
	      
			{if $col % 8 == 0}<tr>{/if} {* begin of new row *}
				{if $tt[tt].class == 'hour'}
					<td class={$tt[tt].midday}hour>{$tt[tt].info}
				{else}
					{if $tt[tt].class == 'today'}
						<td class={$tt[tt].midday}inter_today>
					{else}
						<td class={$tt[tt].midday}inter id="{$tt[tt].id}"> 
					{/if}
					{$tt[tt].info}
					{if $tt[tt].event_num > 0}
						<br>
						{foreach item=event from=$tt[tt].event}
						<div name="events_brief" class=events_brief id="{$event.div_id}">
							<span class="event_drag_handle">X</span>
							{$event.brief}
						</div>
						
						{/foreach}
					{/if}
				{/if}
				</td>
			{if $col % 8 == 7}</tr>{/if} {* end of row *}
			
			
		{/section}
	</table>
