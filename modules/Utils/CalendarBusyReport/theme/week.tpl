<div class="well well-sm">
	<table border="0" cellpadding="0" cellspacing="0"><tr>
		<td class="empty"></td>
		<td class="button_cell"><a class="button" {$prev7_href}>{$prev7_label}&nbsp;&nbsp;<img src="{$theme_dir}/Utils/Calendar/prev2.png"></a></td>
		<td class="button_cell"><a class="button" {$prev_href}>{$prev_label}&nbsp;&nbsp;<img src="{$theme_dir}/Utils/Calendar/prev.png"></a></td>
		<td class="button_cell"><a class="button" {$today_href}>{$today_label}&nbsp;&nbsp;<img src="{$theme_dir}/Utils/Calendar/this.png"></a></td>
		<td class="button_cell"><a class="button" {$next_href}><img border="0" width="8" height="20" src="{$theme_dir}/Utils/Calendar/next.png">&nbsp;&nbsp;{$next_label}</a></td>
		<td class="button_cell"><a class="button" {$next7_href}><img border="0" width="8" height="20" src="{$theme_dir}/Utils/Calendar/next2.png">&nbsp;&nbsp;{$next7_label}</a></td>
		<td style="width: 10px;"></td>
		<td class="button_cell">{$popup_calendar}</td>
		<td class="empty"></td>
		<td class="button_cell">{$navigation_bar_additions}</td>
	</tr></table>
</div>
 
<!-- SHADOW BEGIN -->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="css3_content_shadow">
<!-- -->

<div style="padding: 5px; background-color: #FFFFFF;">

	<table cellspacing=0 id="Utils_Calendar__week">
		<thead>
			<tr>
				<th style="width:{$head_col_width};"></th>
				<th></th>
				<th></th>
				<th></th>
				<th></th>
				<th></th>
				<th></th>
				<th></th>
			</tr>
		</thead>
{* shows month *}
		<tr>
			<td class="hours_header" rowspan="3"><img src="{$theme_dir}/Utils/Calendar/icon-week.png" width="32" height="32" border="0"><br>{$week_view_label}</td>
			<td class="header_month" colspan="{math equation="max(a*b,a)" a=$header_month.first_span.colspan b=$busy_labels|@count}">
				<a {$header_month.first_span.month_link}>{$header_month.first_span.month}</a>
				 &bull;
				<a {$header_month.first_span.year_link}>{$header_month.first_span.year}</a>
			</td>
			{if isset($header_month.second_span)}
				<td class="header_month" colspan="{math equation="max(a*b,a)" a=$header_month.second_span.colspan b=$busy_labels|@count}">
					<a {$header_month.second_span.month_link}>{$header_month.second_span.month}</a>
					 &bull;
					<a {$header_month.second_span.year_link}>{$header_month.second_span.year}</a>
				</td>
			{/if}

		</tr>

{* this row contains days of month *}
		<tr>
			{foreach item=header from=$day_headers}
				<td class="header_day_{$header.style}" colspan="{$busy_labels|@count}"><a {$header.link}>{$header.date}</a></td>
			{/foreach}
		</tr>
		<tr>
		{foreach item=header from=$day_headers}
			{foreach key=k item=label from=$busy_labels}
			<td class="hour">
				{$label}
			</td>
			{/foreach}
		{/foreach}
		</tr>
		<tr>
		{foreach key=k item=stamp from=$timeline}
			<tr>
				<td class="hour" nowrap >{$stamp.label}</td>
				{foreach item=t key=j from=$time_ids}
				{foreach key=k2 item=label from=$busy_labels}
				{assign var=k3 value=$t.$k}
				<td class="inter_{$day_headers.$j.style}"{if $t.$k!==false} time="{$t.$k}"{/if} object="{$k2}">{if isset($report[$k3][$k2])}{$report[$k3][$k2]}{else}&nbsp;{/if}</td>
				{/foreach}
				{/foreach}
			</tr>
		{/foreach}
	</table>

</div>
 		</div>
	</div>
