<!-- SHADIW BEGIN
	<div class="layer" style="padding: 9px; width: 240px;">
		<div class="content_shadow">
 -->

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

<!-- SHADOW END
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
-->
