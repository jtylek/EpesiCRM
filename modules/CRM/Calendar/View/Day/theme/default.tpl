
	<table name=CRMCalendar cellspacing=0 class=week>
{* shows month *}
		<tr>
			<td class="hours_header" rowspan="2"><img src="{$theme_dir}/CRM_Calendar__icon-day.png" width="32" height="32" border="0"><br>Day calendar</td>
			<td class=header_month>{$header_month.info}</td>

		</tr>

{* this row contains days of month *}
		<tr>
			{if $header_day.class == 'today'}
				<td class="header_day">{$header_day.info}</td>
			{else}
				<td class="header_day">{$header_day.info}</td>
			{/if}
		</tr>

{* this row contains timeless events *}
		<tr>
			<td class="hours_header_lower no_border">&nbsp;</td>

			<td class="header_timeless" id="{$timeless_event.id}" onDblClick="{$timeless_event.add}">
			{if $timeless_event.has_events != '0'}
				{foreach item=event from=$timeless_event.event}
				<div name="events_brief" class="events_brief" id="{$event.div_id}">
					<span class="events_drag_handle"><img src="{$theme_dir}/CRM_Calendar__grab.gif" width="15" height="15" border="0" alt="#"></span>
					<span class="events_info" id="{$event.div_id}_brief">{$event.brief}</span>
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
					<td class="{$tt[tt].midday}inter {$tt[tt].has_events}" id="{$tt[tt].id}" onDblClick="{$tt[tt].add}">
						{if $tt[tt].has_events != '0'}
							{foreach item=event from=$tt[tt].event}
								<div name="events_brief" class="events_brief" id="{$event.div_id}">
									<span class="events_drag_handle"><img src="{$theme_dir}/CRM_Calendar__grab.gif" width="15" height="15" border="0" alt="#"></span>
									<span class="events_info" id="{$event.div_id}_brief">{$event.brief}</span>
								</div>
							{/foreach}
						{/if}

				{/if}
				</td>
			{if $col % 2 == 1}</tr>{/if} {* end of row *}


		{/section}
	</table>
