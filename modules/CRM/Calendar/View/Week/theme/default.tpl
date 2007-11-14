	<table class="crm_calendar_week" cellspacing=0>
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
					<td class="header_day_today">{$header_day[header_day].info}</td>
				{else}
					<td class=header_day>{$header_day[header_day].info}</td>
				{/if}
			{/section}
		</tr>

		<tr>
{* this row contains timeless events *}
			<td class=hours_header_lower>&nbsp;</td>

			{foreach item=t_event from=$timeless_event}

				{if $t_event.class == 'today'}
					<td class="header_timeless_today {$t_event.has_events}" id={$t_event.id} onDblClick="{$t_event.add}">
				{else}
					<td class="header_timeless {$t_event.has_events}" id={$t_event.id} onDblClick="{$t_event.add}">
				{/if}
				{if $t_event.event_num > 0}
					{foreach item=event from=$t_event.event}
					<div name="events_brief" class=events_brief id="{$event.div_id}">
							<span class="event_drag_handle">{$event.move}</span>
							<span>{$event.more}</span>
							<span id="{$event.div_id}_brief">{$event.brief}</span>
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
						<td class="{$tt[tt].midday}inter_today {$tt[tt].has_events}" id="{$tt[tt].id}" onDblClick="{$tt[tt].add}">
					{else}
						<td class="{$tt[tt].midday}inter {$tt[tt].has_events}" id="{$tt[tt].id}" onDblClick="{$tt[tt].add}">
					{/if}
					{if $tt[tt].event_num > 0}
						{foreach item=event from=$tt[tt].event}
						<div name="events_brief" class="events_brief" id="{$event.div_id}">
							<span class="event_drag_handle">{$event.move}</span>
							<span>{$event.more}</span>
							<span id="{$event.div_id}_brief">{$event.brief}</span>
						</div>
						{/foreach}
					{/if}
				{/if}
				</td>
			{if $col % 8 == 7}</tr>{/if} {* end of row *}


		{/section}
	</table>
