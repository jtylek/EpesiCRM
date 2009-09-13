<div class="week-menu">
	<table class="week-menu" border="0" cellpadding="0" cellspacing="0"><tr>
		<td class="empty">
			<div id="{$trash_id}" class="trash">
				<div class="icon"><img border="0" width="32" height="32" src="{$theme_dir}/Utils/Calendar/trash.png"></div>
				<div class="text">{$trash_label}</div>
			</div>
		</td>
		<td style="width: 10px;"></td>
		<td><a class="button" {$prev7_href}>{$prev7_label}&nbsp;&nbsp;<img border="0" width="8" height="20" src="{$theme_dir}/Utils/Calendar/prev.png"></a></td>
		<td><a class="button" {$prev_href}>{$prev_label}&nbsp;&nbsp;<img border="0" width="8" height="20" src="{$theme_dir}/Utils/Calendar/prev.png"></a></td>
		<td><a class="button" {$today_href}>{$today_label}&nbsp;&nbsp;<img border="0" width="8" height="20" src="{$theme_dir}/Utils/Calendar/this.png"></a></td>
		<td><a class="button" {$next_href}><img border="0" width="8" height="20" src="{$theme_dir}/Utils/Calendar/next.png">&nbsp;&nbsp;{$next_label}</a></td>
		<td><a class="button" {$next7_href}><img border="0" width="8" height="20" src="{$theme_dir}/Utils/Calendar/next.png">&nbsp;&nbsp;{$next7_label}</a></td>
		<td style="width: 10px;"></td>
		<td>{$popup_calendar}</td>
		<td class="empty"></td>
		<td>{$navigation_bar_additions}</td>
	</tr></table>
</div>
 
<!-- SHADOW BEGIN -->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="content_shadow">
<!-- -->

<div style="padding: 5px; background-color: #FFFFFF;">

	<table cellspacing=0 id="Utils_Calendar__week">
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
                    <td class="inter_{$day_headers.$j.style}"{if $t.$k!==false} id="{$t.$k}"{/if}>&nbsp;</td>
	            {/foreach}
			</tr>
		{/foreach}
	</table>

</div>

<!-- SHADOW END -->
 		</div>
		<div class="shadow-top">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-middle">
			<div class="left"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-bottom">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
	</div>
<!-- -->

<div style="color: #777777; display: block; float: left; padding-left: 20px;">{$info}</div>
