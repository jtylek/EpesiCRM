<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 9px; width: 60%;">
		<div class="content_shadow">
<!-- -->

<div style="padding: 5px; background-color: #FFFFFF;">

	<table name=CRMCalendar cellspacing=0 class=week>
{* shows month *}
		<tr>
			<td class="hours_header" rowspan="2"><img src="{$theme_dir}/Utils_Calendar__icon-day.png" width="32" height="32" border="0"><br>Day calendar</td>
			<td class=header_month>{$header_month}</td>

		</tr>

{* this row contains days of month *}
		<tr>
			<td class="header_day">{$header_day}</td>
		</tr>

{* this row contains timeless events *}
		<tr>
			<td class="hours_header_lower">Timeless</td>

			<td class="header_timeless" id="timeless_eventid">
				Timeless events here.
			</td>
		</tr>

		<tr>
		{foreach key=k item=stamp from=$timeline}
			<tr>
				<td class="hour">{$stamp}</td>
				<td class="inter">...</td>
			</tr>
		{/foreach}

	</table>

</div>

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
