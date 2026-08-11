<div class="navigation-menu">
	<div style="display: flex; align-items: center;">
			<div class="empty"></div>
			<div style="width: 10px;"></div>
			<div class="button_cell"><a class="button" {$prevyear_href}>{$prevyear_label}&nbsp;&nbsp;<img src="{$theme_dir}/Utils/Calendar/prev.png"></a></div>
			<div class="button_cell"><a class="button" {$today_href}>{$today_label}&nbsp;&nbsp;<img src="{$theme_dir}/Utils/Calendar/this.png"></a></div>
			<div class="button_cell"><a class="button" {$nextyear_href}><img src="{$theme_dir}/Utils/Calendar/next.png">&nbsp;&nbsp;{$nextyear_label}</a></div>
			<div style="width: 10px;"></div>
			<div class="button_cell">{$popup_calendar}</div>
			<div class="empty"></div>
			{if $navigation_bar_additions}
				<div class="button_cell">{$navigation_bar_additions}</div>
			{/if}
	</div>
</div>


	<div class="layer" style="padding: 9px; width: 764px;">
		<div class="css3_content_shadow">

{* Was a <table> hand-wrapping every 3 mini month-calendars into a new
   <tr> (a genuine grid of grids) - CSS Grid replaces both levels: the
   outer 3-per-row layout (grid-template-columns: repeat(3,1fr), no more
   col-counter math) and each inner month's own week x day grid (same
   role="table"/"row"/"columnheader"/"cell" pattern as month.tpl/week.tpl -
   see AI-shared/adminlte-theme.md). *}
<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px; background-color: #FFFFFF;">

{foreach item=month from=$year}
		<div>
            <div name="CRMCalendar" id="Utils_Calendar__year" role="table" style="display: grid; grid-template-columns: repeat(8, 1fr);">
            		<div class="header-month" role="row" style="grid-column: 1 / -1;"><a {$month.month_link}>{$month.month_label} &bull; {$month.year_label}</a></div>
            		<div class="week-number" role="columnheader">&nbsp;</div>
            		{foreach item=header from=$day_headers}
            			<div class="header" role="columnheader">{$header}</div>
            		{/foreach}
            	{foreach item=week from=$month.month}
            			<div class="week-number" role="rowheader"><a {$week.week_link}>{$week.week_label}</a></div>
            			{foreach item=day from=$week.days}
            				<div class="day {$day.style}" role="cell"><a {$day.day_link}>{$day.day}</a></div>
            			{/foreach}
            	{/foreach}
            </div>
		</div>

{/foreach}

</div>
 		</div>
	</div>
