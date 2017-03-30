{*

Variable {$weekend} (true/false) indicated whether displayed day is part of weekend or not

*}
<div style="width: 900px;">
 
<div class="well well-sm">
	<table border="0" cellpadding="0" cellspacing="0"><tr>
		<td class="empty"></td>
		<td class="button_cell"><a class="button" {$prev_href}>{$prev_label}&nbsp;&nbsp;<img src="{$theme_dir}/Utils/Calendar/prev.png"></a></td>
		<td class="button_cell"><a class="button" {$today_href}>{$today_label}&nbsp;&nbsp;<img src="{$theme_dir}/Utils/Calendar/this.png"></a></td>
		<td class="button_cell"><a class="button" {$next_href}><img src="{$theme_dir}/Utils/Calendar/next.png">&nbsp;&nbsp;{$next_label}</a></td>
		<td style="width: 10px;"></td>
		<td class="button_cell">{$popup_calendar}</td>
		<td class="empty"></td>
		<td class="button_cell">{$navigation_bar_additions}</td>
	</tr></table>
</div>


	<div class="layer" style="padding: 9px; width: 100%;">
		<div class="css3_content_shadow">

<div style="padding: 5px; background-color: #FFFFFF;">

	<table cellspacing=0 id="Utils_Calendar__day">
		<thead>
			<tr>
				<th style="width:{$head_col_width};"></th>
				<th></th>
			</tr>
		</thead>
{* shows month *}
		<tr>
			<td class="hours_header" rowspan="3"><img src="{$theme_dir}/Utils/Calendar/icon-day.png" width="32" height="32" border="0"><br>{$day_view_label}</td>
			<td class="header_month" colspan="{$busy_labels|@count}">
				<a {$link_month}>{$header_month}</a>
				 &bull;
				<a {$link_year}>{$header_year}</a>
			</td>

		</tr>

{* this row contains days of month *}
		<tr>
			<td class="header_day{if $weekend}_weekend{/if}" colspan="{$busy_labels|@count}">
				{$header_day.label} &bull; {$header_day.number}
			</td>
		</tr>
		<tr>
		{foreach key=k item=label from=$busy_labels}
			<td class="hour">
				{$label}
			</td>
		{/foreach}
		</tr>

		<tr>
		{foreach key=k item=stamp from=$timeline}
			<tr>
				<td class="hour" nowrap >{$stamp.label}</td>
				{foreach key=k item=label from=$busy_labels}
				<td class="inter{if $weekend}_weekend{/if}"{if $stamp.id!==false} time="{$stamp.id}"{/if} object="{$k}">{if isset($report[$stamp.id][$k])}{$report[$stamp.id][$k]}{else}&nbsp;{/if}</td>
				{/foreach}
			</tr>
		{/foreach}

	</table>

</div>
 		</div>
	</div>
</div>
