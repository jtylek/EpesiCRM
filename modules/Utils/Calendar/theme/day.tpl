{*

Variable {$weekend} (true/false) indicated whether displayed day is part of weekend or not

*}
<div style="width: 900px;">
 
<div class="day-menu">
	<table class="day-menu" border="0" cellpadding="0" cellspacing="0"><tr>
		<td class="empty">
			<div id="{$trash_id}" class="trash">
				<div class="icon"><img border="0" width="32" height="32" src="{$theme_dir}/Utils/Calendar/trash.png"></div>
				<div class="text">{$trash_label}</div>
			</div>
		</td>
		<td style="width: 10px;"></td>
		<td><a class="button" {$prev_href}>{$prev_label}&nbsp;&nbsp;<img border="0" width="8" height="20" src="{$theme_dir}/Utils/Calendar/prev.png"></a></td>
		<td><a class="button" {$today_href}>{$today_label}&nbsp;&nbsp;<img border="0" width="8" height="20" src="{$theme_dir}/Utils/Calendar/this.png"></a></td>
		<td><a class="button" {$next_href}><img border="0" width="8" height="20" src="{$theme_dir}/Utils/Calendar/next.png">&nbsp;&nbsp;{$next_label}</a></td>
		<td style="width: 10px;"></td>
		<td>{$popup_calendar}</td>
		<td class="empty"></td>
		<td>{$navigation_bar_additions}</td>
	</tr></table>
</div>


<!-- SHADOW BEGIN -->
	<div class="layer" style="padding: 9px; width: 100%;">
		<div class="content_shadow">
<!-- -->

<div style="padding: 5px; background-color: #FFFFFF;">

	<table cellspacing=0 id="Utils_Calendar__day">
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

</div>
