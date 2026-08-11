<div class="navigation-menu">
	<div style="display: flex; align-items: center;">
			<div class="trash_cell">
				<div id="{$trash_id}" class="trash">
					<div class="icon"><img border="0" width="32" height="32" src="{$theme_dir}/Utils/Calendar/trash.png"></div>
					<div class="text">{$trash_label}</div>
				</div>
			</div>
			<div class="empty"></div>
			<div class="button_cell"><a class="button" {$prev7_href}>{$prev7_label}&nbsp;&nbsp;<img src="{$theme_dir}/Utils/Calendar/prev2.png"></a></div>
			<div class="button_cell"><a class="button" {$prev_href}>{$prev_label}&nbsp;&nbsp;<img src="{$theme_dir}/Utils/Calendar/prev.png"></a></div>
			<div class="button_cell"><a class="button" {$today_href}>{$today_label}&nbsp;&nbsp;<img src="{$theme_dir}/Utils/Calendar/this.png"></a></div>
			<div class="button_cell"><a class="button" {$next_href}><img border="0" width="8" height="20" src="{$theme_dir}/Utils/Calendar/next.png">&nbsp;&nbsp;{$next_label}</a></div>
			<div class="button_cell"><a class="button" {$next7_href}><img border="0" width="8" height="20" src="{$theme_dir}/Utils/Calendar/next2.png">&nbsp;&nbsp;{$next7_label}</a></div>
			<div style="width: 10px;"></div>
			<div class="button_cell">{$popup_calendar}</div>
			<div class="empty"></div>
			<div class="button_cell">{$navigation_bar_additions}</div>
	</div>
</div>

<!-- SHADOW BEGIN -->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="css3_content_shadow">
<!-- -->

<div style="padding: 5px; background-color: #FFFFFF;">

	{* Was a <table> (hour rows x day columns, a genuine 2D data grid, plus a
	   rowspan=2 header cell) - CSS Grid instead, replacing table-cell layout;
	   grid-row:span natively supports what rowspan did, no workaround needed
	   (unlike a flexbox conversion - see AI-shared/adminlte-theme.md). Every
	   row after the header is emitted as a flat sequence of cells, not
	   wrapped in its own row div: Grid's own row-major auto-placement fills
	   each new row starting at column 1 automatically, the same way table
	   rows did, with no explicit row container needed. *}
	<div id="Utils_Calendar__week" role="table" style="display: grid; grid-template-columns: {$head_col_width} repeat(7, 1fr);">
{* shows month *}
			<div class="hours_header" role="rowheader" style="grid-row: span 2;"><img src="{$theme_dir}/Utils/Calendar/icon-week.png" width="32" height="32" border="0"><br>{$week_view_label}</div>
			<div class="header_month" role="columnheader" style="grid-column: span {$header_month.first_span.colspan};">
				<a {$header_month.first_span.month_link}>{$header_month.first_span.month}</a>
				 &bull;
				<a {$header_month.first_span.year_link}>{$header_month.first_span.year}</a>
			</div>
			{if isset($header_month.second_span)}
				<div class="header_month" role="columnheader" style="grid-column: span {$header_month.second_span.colspan};">
					<a {$header_month.second_span.month_link}>{$header_month.second_span.month}</a>
					 &bull;
					<a {$header_month.second_span.year_link}>{$header_month.second_span.year}</a>
				</div>
			{/if}

{* this row contains days of month *}
			{foreach item=header from=$day_headers}
				<div class="header_day_{$header.style}" role="columnheader"><a {$header.link}>{$header.date}</a></div>
			{/foreach}

		{foreach key=k item=stamp from=$timeline}
			{* display:contents keeps this row hoverable as a group (see
			   week.css's ".week-row:hover .hour") while its cells still lay
			   out directly against the grid's own column tracks, same
			   technique as GenericBrowser's row wrapper (see
			   AI-shared/adminlte-theme.md). *}
			<div class="week-row" role="row" style="display: contents;">
				<div class="hour" role="rowheader" style="white-space: nowrap;">{$stamp.label}</div>
				{foreach item=t key=j from=$time_ids}
                    <div class="inter_{$day_headers.$j.style}" role="cell"{if $t.$k!==false} id="{$t.$k}"{/if}><div class="inner">&nbsp;</div></div>
	            {/foreach}
			</div>
		{/foreach}
	</div>

</div>
 		</div>
	</div>

<div style="color: #777777; display: block; float: left; padding-left: 20px;">{$info}</div>
