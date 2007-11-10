
	<table name=CRMCalendar cellspacing=0 class=week>
{* shows month *}
		<tr>
			<td width=5% class=hours_header>&nbsp;</td> 
			<td class=header_month>{$header_month.info}</td>
			
		</tr>
		
{* this row contains days of month *}
		<tr>
			<td class=hours_header>&nbsp;</td>
			{if $header_day.class == 'today'}
				<td class=header_day>{$header_day.info}</td>
			{else}
				<td class=header_day>{$header_day.info}</td>
			{/if}
		</tr>
		
{* this row contains timeless events *}
		<tr>
			<td class=hours_header_lower>&nbsp;</td>
			
			<td class=header_timeless id="{$timeless_event.id}">
			{if $timeless_event.event_num > 0}
				{foreach item=event from=$timeless_event.event}
				<div name="events_brief" class=events_brief id="{$event.div_id}">
					<span class="event_drag_handle">X</span>
					{$event.brief}
				</div>
				{/foreach}
			{/if}
			</td>
		</tr>
		
		<tr>
		{* timetable *}
		{section name=tt loop=$tt}
			
			{* $col is the current column *}
	      	{math assign="col" equation="x-1" x=$smarty.section.tt.rownum}
	      
			{if $col % 2 == 0}<tr>{/if} {* begin of new row *}
				{if $tt[tt].class == 'hour'}
					<td class={$tt[tt].midday}hour>{$tt[tt].info}
				{else}
					<td class={$tt[tt].midday}inter id="{$tt[tt].id}" >
							{$tt[tt].info}
						{if $tt[tt].event_num > 0}
							{foreach item=event from=$tt[tt].event}
								<div name="events_brief" class=events_brief id="{$event.div_id}">
									<span class="event_drag_handle">X</span>
									{$event.brief}
								</div>
							{/foreach}
						{/if}
				
				{/if}
				</td>
			{if $col % 2 == 1}</tr>{/if} {* end of row *}
			
			
		{/section}
	</table>