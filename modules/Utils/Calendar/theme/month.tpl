<div class="navigation-menu">
	<div style="display: flex; align-items: center;">
			<div class="trash_cell">
				<div id="{$trash_id}" class="trash">
					<div class="icon"><img border="0" width="32" height="32" src="{$theme_dir}/Utils/Calendar/trash.png"></div>
					<div class="text">{$trash_label}</div>
				</div>
			</div>
			<div class="empty"></div>
			<div class="button_cell"><a class="button" {$prevyear_href}>{$prevyear_label}&nbsp;&nbsp;<img src="{$theme_dir}/Utils/Calendar/prev2.png"></a></div>
			<div class="button_cell"><a class="button" {$prevmonth_href}>{$prevmonth_label}&nbsp;&nbsp;<img src="{$theme_dir}/Utils/Calendar/prev.png"></a></div>
			<div class="button_cell"><a class="button" {$today_href}>{$today_label}&nbsp;&nbsp;<img src="{$theme_dir}/Utils/Calendar/this.png"></a></div>
			<div class="button_cell"><a class="button" {$nextmonth_href}><img src="{$theme_dir}/Utils/Calendar/next.png">&nbsp;&nbsp;{$nextmonth_label}</a></div>
			<div class="button_cell"><a class="button" {$nextyear_href}><img src="{$theme_dir}/Utils/Calendar/next2.png">&nbsp;&nbsp;{$nextyear_label}</a></div>
			<div style="width: 10px;"></div>
			<div class="button_cell">{$popup_calendar}</div>
			<div class="empty"></div>
			<div class="button_cell">{$navigation_bar_additions}</div>
	</div>
</div>

<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="css3_content_shadow">
<!-- -->

<div style="padding: 5px; background-color: #FFFFFF;">

	{* Was a <table> (week rows x day columns, a genuine 2D data grid) - CSS
	   Grid instead, replacing table-cell layout; role="table"/"row"/
	   "columnheader"/"cell" restore ARIA table semantics for screen readers.
	   Each row div is display:contents so its cells still lay out directly
	   against the grid's own column tracks (no jQuery drag/drop or cloning
	   of a whole row depends on the row being a real box here - unlike
	   GenericBrowser's grid, see AI-shared/adminlte-theme.md - only
	   individual day cells are drag/drop targets, by id, tag-independent). *}
	<div name="CRMCalendar" id="Utils_Calendar__month" role="table" style="display: grid; grid-template-columns: 30px repeat(7, 1fr);">
		<div class="month-header" role="row" style="grid-column: 1 / -1;">{$month_label} &bull; <a {$year_link}>{$year_label}</a></div>

		<div role="row" style="display: contents;">
			<div class="week-number" role="columnheader">&nbsp;</div>
			{foreach item=header from=$day_headers}
                <div class="{$header.class}" role="columnheader">{$header.label}</div>
			{/foreach}
		</div>

		{foreach item=week from=$month}
			<div role="row" style="display: contents;">
				<div class="week-number" role="rowheader"><a {$week.week_link}>{$week.week_label}</a></div>
				{foreach item=day from=$week.days}
					<div class="day {$day.style}" role="cell"><div class="inner" id="{$day.id}"><a class="day_link" {$day.day_link}>{$day.day}</a></div></div>
				{/foreach}
			</div>
		{/foreach}
	</div>

</div>

<!-- SHADOW END -->
 		</div>
	</div>
<!-- -->

<div style="color: #777777; display: block; float: left; padding-left: 20px;">{$info}</div>
