<div class="well well-sm">
	<div id="{$trash_id}" class="btn btn-warning">
		<i class="fa fa-trash" aria-hidden="true"></i>{$trash_label}
	</div>

	<a class="btn btn-default" {$prevyear_href}>{$prevyear_label}&nbsp;<i class="fa fa-fast-backward" aria-hidden="true"></i></a>
	<a class="btn btn-default" {$prevmonth_href}>{$prevmonth_label}&nbsp;<i class="fa fa-backward" aria-hidden="true"></i></a>
	<a class="btn btn-default" {$today_href}>{$today_label}&nbsp;<i class="fa fa-play fa-rotate-90" aria-hidden="true"></i></a>
	<a class="btn btn-default" {$nextmonth_href}><i class="fa fa-forward" aria-hidden="true"></i>&nbsp;{$nextmonth_label}</a>
	<a class="btn btn-default" {$nextyear_href}><i class="fa fa-fast-forward" aria-hidden="true"></i>&nbsp;{$nextyear_label}</a>
	{$popup_calendar}
	<div class="pull-right">
	{$navigation_bar_additions}
	</div>
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
