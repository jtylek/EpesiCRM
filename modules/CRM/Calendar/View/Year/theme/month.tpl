	<table name="CRMCalendar" id="year" cellpadding="0" cellspacing="0" border="0">
		<tr>
			<td colspan="8" class="header-month">{$name}</td>
		</tr>
		<tr>
		{section name=header loop=$header}
			<td class={$header[header].class}>{$header[header].cnt}</td>
		{/section}
		</tr>
		{foreach item=days from=$weeks}

	      	<tr>
			{section name=days loop=$days}
				{if $days[days].class == 'today'}
					<td class="today">{$days[days].info}
				{elseif $days[days].class == 'week_number'}
					<td class="week-number">{$days[days].info}
				{else}
					<td class="day">{$days[days].info}
				{/if}
				</td>
			{/section}
			</tr>

		{/foreach}
	</table>

