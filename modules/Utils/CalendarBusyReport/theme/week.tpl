<div class="navigation-menu">
	<div style="display: flex; align-items: center;">
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

	{* Was a <table> (hour rows x day columns, each day sub-divided into
	   |$busy_labels| resource columns via colspan) plus a malformed extra
	   <tr> that opened around every per-stamp row without ever being closed
	   (browsers silently auto-closed it - not reproduced here). CSS Grid
	   replaces both the outer day columns and the colspan-driven resource
	   sub-columns: same recipe as Utils_Calendar's own week.tpl, with each
	   grid-column span multiplied by the resource count (see
	   AI-shared/adminlte-theme.md). *}
	<div id="Utils_Calendar__week" role="table" style="display: grid; grid-template-columns: {$head_col_width} repeat({math equation="a*b" a=$day_headers|@count b=$busy_labels|@count}, 1fr);">
{* shows month *}
			<div class="hours_header" role="rowheader" style="grid-row: span 3;"><img src="{$theme_dir}/Utils/Calendar/icon-week.png" width="32" height="32" border="0"><br>{$week_view_label}</div>
			<div class="header_month" role="columnheader" style="grid-column: span {math equation="max(a*b,a)" a=$header_month.first_span.colspan b=$busy_labels|@count};">
				<a {$header_month.first_span.month_link}>{$header_month.first_span.month}</a>
				 &bull;
				<a {$header_month.first_span.year_link}>{$header_month.first_span.year}</a>
			</div>
			{if isset($header_month.second_span)}
				<div class="header_month" role="columnheader" style="grid-column: span {math equation="max(a*b,a)" a=$header_month.second_span.colspan b=$busy_labels|@count};">
					<a {$header_month.second_span.month_link}>{$header_month.second_span.month}</a>
					 &bull;
					<a {$header_month.second_span.year_link}>{$header_month.second_span.year}</a>
				</div>
			{/if}

{* this row contains days of month *}
			{foreach item=header from=$day_headers}
				<div class="header_day_{$header.style}" role="columnheader" style="grid-column: span {$busy_labels|@count};"><a {$header.link}>{$header.date}</a></div>
			{/foreach}

{* this row contains the per-resource sub-headers under each day *}
			{foreach item=header from=$day_headers}
				{foreach key=k item=label from=$busy_labels}
				<div class="hour" role="columnheader">
					{$label}
				</div>
				{/foreach}
			{/foreach}

		{foreach key=k item=stamp from=$timeline}
			<div class="week-row" role="row" style="display: contents;">
				<div class="hour" role="rowheader" style="white-space: nowrap;">{$stamp.label}</div>
				{foreach item=t key=j from=$time_ids}
				{foreach key=k2 item=label from=$busy_labels}
				{assign var=k3 value=$t.$k}
				<div class="inter_{$day_headers.$j.style}" role="cell"{if $t.$k!==false} time="{$t.$k}"{/if} object="{$k2}">{if isset($report[$k3][$k2])}{$report[$k3][$k2]}{else}&nbsp;{/if}</div>
				{/foreach}
				{/foreach}
			</div>
		{/foreach}
	</div>

</div>
 		</div>
	</div>
