{*

Variable {$weekend} (true/false) indicated whether displayed day is part of weekend or not

*}
<div style="width: 900px;">

<div class="navigation-menu">
	<div style="display: flex; align-items: center;">
			<div class="empty"></div>
			<div class="button_cell"><a class="button" {$prev_href}>{$prev_label}&nbsp;&nbsp;<img src="{$theme_dir}/Utils/Calendar/prev.png"></a></div>
			<div class="button_cell"><a class="button" {$today_href}>{$today_label}&nbsp;&nbsp;<img src="{$theme_dir}/Utils/Calendar/this.png"></a></div>
			<div class="button_cell"><a class="button" {$next_href}><img src="{$theme_dir}/Utils/Calendar/next.png">&nbsp;&nbsp;{$next_label}</a></div>
			<div style="width: 10px;"></div>
			<div class="button_cell">{$popup_calendar}</div>
			<div class="empty"></div>
			<div class="button_cell">{$navigation_bar_additions}</div>
	</div>
</div>


	<div class="layer" style="padding: 9px; width: 100%;">
		<div class="css3_content_shadow">

<div style="padding: 5px; background-color: #FFFFFF;">

	{* Was a <table> (hour rows x a single day column sub-divided into
	   |$busy_labels| resource columns via colspan, plus a rowspan=3 header
	   cell) plus a malformed extra <tr> that opened around every per-stamp
	   row without ever being closed (browsers silently auto-closed it - not
	   reproduced here). CSS Grid replaces both the rowspan and the
	   colspan-driven resource sub-columns, same recipe as
	   CalendarBusyReport's own week.tpl (see AI-shared/adminlte-theme.md). *}
	<div id="Utils_Calendar__day" role="table" style="display: grid; grid-template-columns: {$head_col_width} repeat({$busy_labels|@count}, 1fr);">
{* shows month *}
			<div class="hours_header" role="rowheader" style="grid-row: span 3;"><img src="{$theme_dir}/Utils/Calendar/icon-day.png" width="32" height="32" border="0"><br>{$day_view_label}</div>
			<div class="header_month" role="columnheader" style="grid-column: span {$busy_labels|@count};">
				<a {$link_month}>{$header_month}</a>
				 &bull;
				<a {$link_year}>{$header_year}</a>
			</div>

{* this row contains days of month *}
			<div class="header_day{if $weekend}_weekend{/if}" role="columnheader" style="grid-column: span {$busy_labels|@count};">
				{$header_day.label} &bull; {$header_day.number}
			</div>

{* this row contains the per-resource sub-headers *}
			{foreach key=k item=label from=$busy_labels}
			<div class="hour" role="columnheader">
				{$label}
			</div>
			{/foreach}

		{foreach key=k item=stamp from=$timeline}
			<div class="day-row" role="row" style="display: contents;">
				<div class="hour" role="rowheader" style="white-space: nowrap;">{$stamp.label}</div>
				{foreach key=k item=label from=$busy_labels}
				<div class="inter{if $weekend}_weekend{/if}" role="cell"{if $stamp.id!==false} time="{$stamp.id}"{/if} object="{$k}">{if isset($report[$stamp.id][$k])}{$report[$stamp.id][$k]}{else}&nbsp;{/if}</div>
				{/foreach}
			</div>
		{/foreach}

	</div>

</div>
 		</div>
	</div>
</div>
