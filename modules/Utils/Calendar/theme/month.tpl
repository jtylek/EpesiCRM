<div class="navigation-menu">
	<table border="0" cellpadding="0" cellspacing="0"><tr>
		<td class="trash_cell">
			<div id="{$trash_id}" class="trash">
				<div class="icon"><img border="0" width="32" height="32" src="{$theme_dir}/Utils/Calendar/trash.png"></div>
				<div class="text">{$trash_label}</div>
			</div>
		</td>
		<td class="empty"></td>
		<td class="button_cell"><a class="button" {$prevyear_href}>{$prevyear_label}&nbsp;&nbsp;<img src="{$theme_dir}/Utils/Calendar/prev2.png"></a></td>
		<td class="button_cell"><a class="button" {$prevmonth_href}>{$prevmonth_label}&nbsp;&nbsp;<img src="{$theme_dir}/Utils/Calendar/prev.png"></a></td>
		<td class="button_cell"><a class="button" {$today_href}>{$today_label}&nbsp;&nbsp;<img src="{$theme_dir}/Utils/Calendar/this.png"></a></td>
		<td class="button_cell"><a class="button" {$nextmonth_href}><img src="{$theme_dir}/Utils/Calendar/next.png">&nbsp;&nbsp;{$nextmonth_label}</a></td>
		<td class="button_cell"><a class="button" {$nextyear_href}><img src="{$theme_dir}/Utils/Calendar/next2.png">&nbsp;&nbsp;{$nextyear_label}</a></td>
		<td style="width: 10px;"></td>
		<td class="button_cell">{$popup_calendar}</td>
		<td class="empty"></td>
		<td class="button_cell">{$navigation_bar_additions}</td>
	</tr></table>
</div>

<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="css3_content_shadow">
<!-- -->

<div style="padding: 5px; background-color: #FFFFFF;">

	<table name="CRMCalendar" id="Utils_Calendar__month" cellpadding="0" cellspacing="0" border="0">
		<thead>
			<tr>
				<th style="width:30px;"></th>
				<th></th>
				<th></th>
				<th></th>
				<th></th>
				<th></th>
				<th></th>
				<th></th>
			</tr>
		</thead>
		<tr>
			<td class="month-header" colspan="8">{$month_label} &bull; <a {$year_link}>{$year_label}</a></td>
		</tr>

		<tr>
			<td class="week-number">&nbsp;</td>
			{foreach item=header from=$day_headers}
                <td class="{$header.class}">{$header.label}</td>
			{/foreach}
		</tr>

		{foreach item=week from=$month}
			<tr>
				<td class="week-number"><a {$week.week_link}>{$week.week_label}</a></td>
				{foreach item=day from=$week.days}
					<td class="day {$day.style}"><div class="inner" id="{$day.id}"><a class="day_link" {$day.day_link}>{$day.day}</a></div></td>
				{/foreach}
			</tr>
		{/foreach}
	</table>

</div>

<!-- SHADOW END -->
 		</div>
	</div>
<!-- -->

<div style="color: #777777; display: block; float: left; padding-left: 20px;">{$info}</div>
