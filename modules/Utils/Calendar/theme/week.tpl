<div class="well well-sm">
	<div id="{$trash_id}" class="btn btn-warning">
		<i class="fa fa-trash" aria-hidden="true"></i>{$trash_label}
	</div>

	<a class="btn btn-default" {$prev7_href}>{$prev7_label}&nbsp;<i class="fa fa-fast-backward" aria-hidden="true"></i></a>
	<a class="btn btn-default" {$prev_href}>{$prev_label}&nbsp;<i class="fa fa-backward" aria-hidden="true"></i></a>
	<a class="btn btn-default" {$today_href}>{$today_label}&nbsp;<i class="fa fa-play fa-rotate-90" aria-hidden="true"></i></a>
	<a class="btn btn-default" {$next_href}><i class="fa fa-forward" aria-hidden="true"></i>&nbsp;{$next_label}</a>
	<a class="btn btn-default" {$next7_href}><i class="fa fa-fast-forward" aria-hidden="true"></i>&nbsp;{$next7_label}</a>
	{$popup_calendar}
	<div class="pull-right">
	{$navigation_bar_additions}
	</div>
</div>

<!-- SHADOW BEGIN -->
<!-- -->

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
			<td class="hours_header" rowspan="2"><img src="{$theme_dir}/Utils/Calendar/icon-week.png" width="32" height="32" border="0"><br>{$week_view_label}</td>
			<td class="header_month" colspan="{$header_month.first_span.colspan}">
				<a {$header_month.first_span.month_link}>{$header_month.first_span.month}</a>
				 &bull;
				<a {$header_month.first_span.year_link}>{$header_month.first_span.year}</a>
			</td>
			{if isset($header_month.second_span)}
				<td class="header_month" colspan="{$header_month.second_span.colspan}">
					<a {$header_month.second_span.month_link}>{$header_month.second_span.month}</a>
					 &bull;
					<a {$header_month.second_span.year_link}>{$header_month.second_span.year}</a>
				</td>
			{/if}

		</tr>

{* this row contains days of month *}
		<tr>
			{foreach item=header from=$day_headers}
				<td class="header_day_{$header.style}"><a {$header.link}>{$header.date}</a></td>
			{/foreach}
		</tr>
		<tr>
		{foreach key=k item=stamp from=$timeline}
			<tr>
				<td class="hour" nowrap >{$stamp.label}</td>
				{foreach item=t key=j from=$time_ids}
                    <td class="inter_{$day_headers.$j.style}"{if $t.$k!==false} id="{$t.$k}"{/if}><div class="inner">&nbsp;</div></td>
	            {/foreach}
			</tr>
		{/foreach}
	</table>

<div style="color: #777777; display: block; float: left; padding-left: 20px;">{$info}</div>
