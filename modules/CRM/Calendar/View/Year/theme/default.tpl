	
	<table name=CRMCalendar id=year>
		<tr>
		{section name=header loop=$header}
			<td class={$header[header].class}>{$header[header].cnt}</td>
		{/section}
		</tr>
		{foreach item=days from=$weeks}
			
	      	<tr>
			{section name=days loop=$days}
				{if $days[days].class == 'today'}
					<td class=today>{$days[days].info}
				{elseif $days[days].class == 'week_number'}
					<td class=header>{$days[days].info}
				{else}
					<td class=day>{$days[days].info}
				{/if}
				</td>
			{/section}
			</tr>
			
		{/foreach}
	</table>
