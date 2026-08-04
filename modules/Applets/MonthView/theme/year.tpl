<center>
<div class="month-applet-menu">
		<div style="display: flex; align-items: center;">
				<div><a class="button" {$prevyear_href}><img src="{$theme_dir}/Utils/Calendar/prev.png"></a></div>
				<div><a class="button" {$today_href}><img src="{$theme_dir}/Utils/Calendar/this.png"></a></div>
				<div><a class="button" {$nextyear_href}><img src="{$theme_dir}/Utils/Calendar/next.png"></a></div>
				<div class="select_date_dashboard">{$popup_calendar}</div>
		</div>
</div>

{* Was a <table> hand-wrapping every 3 mini month-calendars into a new <tr>
   (a genuine grid of grids) - CSS Grid replaces both levels, same recipe as
   Utils_Calendar's own year.tpl (see AI-shared/adminlte-theme.md). *}
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
	</center>
