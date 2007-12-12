<div id="month-menu">
	<table border="0"><tr>
                <td class="add-info"><div style="width:180px;border: 1px solid gray" id="{$trash_id}">trash</div></td>
		<td class="empty"></td>
		<td style="width: 10px;"></td>
		<td><a class="button" {$prevyear_href}><img border="0" width="8" height="8" src="{$theme_dir}/Utils_Calendar__prev.png">&nbsp;&nbsp;{$prevyear_label}</a></td>
		<td><a class="button" {$prevmonth_href}><img border="0" width="8" height="8" src="{$theme_dir}/Utils_Calendar__prev.png">&nbsp;&nbsp;{$prevmonth_label}</a></td>
		<td><a class="button" {$today_href}>{$today_label}&nbsp;&nbsp;<img border="0" width="8" height="8" src="{$theme_dir}/Utils_Calendar__this.png"></a></td>
		<td><a class="button" {$nextmonth_href}>{$nextmonth_label}&nbsp;&nbsp;<img border="0" width="8" height="8" src="{$theme_dir}/Utils_Calendar__next.png"></a></td>
		<td><a class="button" {$nextyear_href}>{$nextyear_label}&nbsp;&nbsp;<img border="0" width="8" height="8" src="{$theme_dir}/Utils_Calendar__next.png"></a></td>
		<td style="width: 10px;"></td>
		<td>{$popup_calendar}</td>
		<td class="empty"></td>
		<td class="add-info">{$info}</td>
	</tr></table>
</div>

<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="content_shadow">
<!-- -->

<div style="padding: 5px; background-color: #FFFFFF;">

	<table name="CRMCalendar" id="Utils_Calendar__month" cellpadding="0" cellspacing="0" border="0">
		<tr>
			<td class="month-header" rowspan="2">&nbsp;<!-- <img src="{$theme_dir}/Utils_Calendar__icon.png" width="32" height="32" border="0"> --></td>
			<td class="month-header" colspan="7">{$month_label} &bull; <a {$year_link}>{$year_label}</a></td>
		</tr>

		<tr>
			{foreach item=header from=$day_headers}
                <td class="header">{$header}</td>
			{/foreach}
		</tr>

		{foreach item=week from=$month}
			<tr>
				<td class="week-number"><a {$week.week_link}>{$week.week_label}</a></td>
				{foreach item=day from=$week.days}
					<td class="day {$day.style}" id="{$day.id}"><a {$day.day_link}>{$day.day}</a></td>
				{/foreach}
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
