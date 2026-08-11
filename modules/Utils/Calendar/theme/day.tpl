{*

Variable {$weekend} (true/false) indicated whether displayed day is part of weekend or not

*}
<div style="width: 900px;">

<div class="navigation-menu">
	<div style="display: flex; align-items: center;">
			<div class="empty">
				<div id="{$trash_id}" class="trash">
					<div class="icon"><img border="0" width="32" height="32" src="{$theme_dir}/Utils/Calendar/trash.png"></div>
					<div class="text">{$trash_label}</div>
				</div>
			</div>
			<div style="width: 10px;"></div>
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

	{* Was a <table> (hour rows x a single day column, plus a rowspan=2
	   header cell) - CSS Grid instead, replacing table-cell layout;
	   grid-row:span natively supports what rowspan did (see
	   AI-shared/adminlte-theme.md). Every row after the header is a flat
	   sequence of cells, not wrapped in its own row div: Grid's own
	   row-major auto-placement fills each new row starting at column 1
	   automatically, same as table rows did. *}
	<div id="Utils_Calendar__day" role="table" style="display: grid; grid-template-columns: {$head_col_width} 1fr;">
{* shows month *}
			<div class="hours_header" role="rowheader" style="grid-row: span 2;"><img src="{$theme_dir}/Utils/Calendar/icon-day.png" width="32" height="32" border="0"><br>{$day_view_label}</div>
			<div class="header_month" role="columnheader">
				<a {$link_month}>{$header_month}</a>
				 &bull;
				<a {$link_year}>{$header_year}</a>
			</div>

{* this row contains days of month *}
			<div class="header_day{if $weekend}_weekend{/if}" role="columnheader">
				{$header_day.label} &bull; {$header_day.number}
			</div>

		{foreach key=k item=stamp from=$timeline}
			<div class="day-row" role="row" style="display: contents;">
				<div class="hour" role="rowheader" style="white-space: nowrap;">{$stamp.label}</div>
				<div class="inter{if $weekend}_weekend{/if}" role="cell"{if $stamp.id!==false} id="{$stamp.id}"{/if}>&nbsp;</div>
			</div>
		{/foreach}

	</div>

</div>
 		</div>
	</div>
<div style="color: #777777; display: block; float: left; padding-left: 20px;">{$info}</div>

</div>
