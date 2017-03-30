{*

Variable {$weekend} (true/false) indicated whether displayed day is part of weekend or not

*}
<div class="well well-sm">
	<div id="{$trash_id}" class="btn btn-warning">
		<i class="fa fa-trash" aria-hidden="true"></i>{$trash_label}
	</div>

	<a class="btn btn-default" {$prev_href}>{$prev_label}&nbsp;<i class="fa fa-backward" aria-hidden="true"></i></a>
	<a class="btn btn-default" {$today_href}>{$today_label}&nbsp;<i class="fa fa-play fa-rotate-90" aria-hidden="true"></i></a>
	<a class="btn btn-default" {$next_href}><i class="fa fa-forward" aria-hidden="true"></i>&nbsp;{$next_label}</a>
	{$popup_calendar}
	<div class="pull-right">
	{$navigation_bar_additions}
	</div>
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
			<td class="hours_header" rowspan="2"><img src="{$theme_dir}/Utils/Calendar/icon-day.png" width="32" height="32" border="0"><br>{$day_view_label}</td>
			<td class="header_month">
				<a {$link_month}>{$header_month}</a>
				 &bull;
				<a {$link_year}>{$header_year}</a>
			</td>

		</tr>

{* this row contains days of month *}
		<tr>
			<td class="header_day{if $weekend}_weekend{/if}">
				{$header_day.label} &bull; {$header_day.number}
			</td>
		</tr>

		<tr>
		{foreach key=k item=stamp from=$timeline}
			<tr>
				<td class="hour" nowrap >{$stamp.label}</td>
				<td class="inter{if $weekend}_weekend{/if}"{if $stamp.id!==false} id="{$stamp.id}"{/if}>&nbsp;</td>
			</tr>
		{/foreach}

	</table>

</div>
 		</div>
	</div>
<div style="color: #777777; display: block; float: left; padding-left: 20px;">{$info}</div>
