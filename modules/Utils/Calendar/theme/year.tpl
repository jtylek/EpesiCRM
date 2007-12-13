<div class="year-menu">
	<table border="0" class="menu"><tr>
		<td class="empty"></td>
		<td style="width: 10px;"></td>
		<td><a class="button" {$prevyear_href}>{$prevyear_label}&nbsp;&nbsp;<img border="0" width="8" height="8" src="{$theme_dir}/Utils_Calendar__prev.png"></a></td>
		<td><a class="button" {$today_href}>{$today_label}&nbsp;&nbsp;<img border="0" width="8" height="8" src="{$theme_dir}/Utils_Calendar__this.png"></a></td>
		<td><a class="button" {$nextyear_href}><img border="0" width="8" height="8" src="{$theme_dir}/Utils_Calendar__next.png">&nbsp;&nbsp;{$nextyear_label}</a></td>
		<td style="width: 10px;"></td>
		<td>{$popup_calendar}</td>
		<td class="empty"></td>
	</tr></table>
</div>


<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 9px; width: 766px;">
		<div class="content_shadow">
<!-- -->

{math assign="col" equation="x" x=3}

<table border="0" cellpadding="0" cellspacing="5" style="background-color: #FFFFFF;">

{foreach item=month from=$year}
	{if $col % 3 == 0}<tr>{/if}
		<td style="vertical-align: top">
            <table name="CRMCalendar" id="Utils_Calendar__year" cellpadding="0" cellspacing="0" border="0">
            	<tr>
            		<td class="header-month" rowspan="2">&nbsp;</td>
            		<td class="header-month" colspan="7"><a {$month.month_link}>{$month.month_label} &bull; {$month.year_label}</a></td>
            	</tr>
            	<tr>
            		{foreach item=header from=$day_headers}
            			<td class="header">{$header}</td>
            		{/foreach}
            	</tr>
            	{foreach item=week from=$month.month}
            		<tr>
            			<td class="week-number"><a {$week.week_link}>{$week.week_label}</a></td>
            			{foreach item=day from=$week.days}
            				<td class="day {$day.style}"><a {$day.day_link}>{$day.day}</a></td>
            			{/foreach}
            		</tr>
            	{/foreach}
            </table>
		</td>
	{if $col % 3 == 3}</tr>{/if}

    {math assign="col" equation="x+1" x=$col}

{/foreach}

</table>

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
