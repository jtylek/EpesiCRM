<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="content_shadow">
<!-- -->

	<table name="CRMCalendar" class="crm_calendar_month" cellpadding="0" cellspacing="0" border="0">
		<tr>
		{section name=header loop=$header}
			<td class="{$header[header].class}">{$header[header].cnt}</td>
		{/section}
		</tr>
		{foreach item=days from=$weeks}

	      	<tr>
			{foreach item=day from=$days}
				{if $day.class == 'today'}
					<td class="today">{$day.info}
				{elseif $day.class == 'week_number'}
					<td class="week-number">{$day.info}
				{else}
					<td class="day">{$day.info}
				{/if}
				{if $day.event_num > 0}
					{foreach item=event from=$day.event}
					<div name="events_brief" class="events_brief" id="{$event.div_id}">
						<span class="events_drag_handle">{$event.move}</span>
						<span class="events_brief_info" id="{$event.div_id}_brief">{$event.brief}</span>
						<span class="events_more">{$event.more}</span>
					</div>
					{/foreach}
				{/if}
				</td>
			{/foreach}
			</tr>

		{/foreach}
	</table>

<!-- SHADOW END -->
 		</div>
		<div class="shadow-top">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-middle">
			<div class="left"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-bottom">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
	</div>
<!-- -->
