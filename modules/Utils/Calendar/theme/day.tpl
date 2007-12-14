<div class="day-menu">
	<table border="0" class="menu"><tr>
		<td class="add-info">
			<div id="{$trash_id}" class="trash">
				<div class="icon"><img border="0" width="32" height="32" src="{$theme_dir}/Utils_Calendar__trash.png"></div>
				<div class="text">Drag and drop<br>to delete</div>
			</div>
		</td>
		<td class="empty"></td>
		<td style="width: 10px;"></td>
		<td><a class="button" {$prev_href}>{$prev_label}&nbsp;&nbsp;<img border="0" width="8" height="8" src="{$theme_dir}/Utils_Calendar__prev.png"></a></td>
		<td><a class="button" {$today_href}>{$today_label}&nbsp;&nbsp;<img border="0" width="8" height="8" src="{$theme_dir}/Utils_Calendar__this.png"></a></td>
		<td><a class="button" {$next_href}><img border="0" width="8" height="8" src="{$theme_dir}/Utils_Calendar__next.png">&nbsp;&nbsp;{$next_label}</a></td>
		<td style="width: 10px;"></td>
		<td>{$popup_calendar}</td>
		<td class="empty"></td>
		<td class="add-info">{$info}</td>
	</tr></table>
</div>


<!-- SHADOW BEGIN -->
	<div class="layer" style="padding: 9px; width: 60%;">
		<div class="content_shadow">
<!-- -->

<div style="padding: 5px; background-color: #FFFFFF;">

	<table cellspacing=0 id="Utils_Calendar__day">
{* shows month *}
		<tr>
			<td class="hours_header" rowspan="2"><img src="{$theme_dir}/Utils_Calendar__icon-day.png" width="32" height="32" border="0"><br>{$day_view_label}</td>
			<td class=header_month>
				<a {$link_month}>{$header_month}</a>
				 &bull;
				<a {$link_year}>{$header_year}</a>
			</td>

		</tr>

{* this row contains days of month *}
		<tr>
			<td class="header_day">
				{$header_day.number} {$header_day.label}
			</td>
		</tr>

		<tr>
		{foreach key=k item=stamp from=$timeline}
			<tr>
				<td class="hour">{$stamp.label}</td>
				<td class="inter" id="{$stamp.id}"></td>
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
