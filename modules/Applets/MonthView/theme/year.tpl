<center>
<div class="month-applet-menu">
		<table border="0" class="month-applet-menu">
			<tr>
				<td><a class="button" {$prevyear_href}><img border="0" width="8" height="20" src="{$theme_dir}/Utils/Calendar/prev.png"></a></td>
				<td><a class="button" {$today_href}><img border="0" width="8" height="20" src="{$theme_dir}/Utils/Calendar/this.png"></a></td>
				<td><a class="button" {$nextyear_href}><img border="0" width="8" height="20" src="{$theme_dir}/Utils/Calendar/next.png"></a></td>
				<td>{$popup_calendar}</td>
				<!-- <td style="width: 10px;"></td>
				<td><a class="button" style="width: 80px;"><img border="0" width="20" height="20" src="{$theme_dir}/Utils/Calendar/4x3.png" style="vertical-align: middle; padding: 0px; margin-left: 10px; display: block; float: left; width: 20px; height: 20px;">4 x 3</a></td> -->
			</tr>
		</table>
</div>

{math assign="col" equation="x" x=3}

<table border="0" cellpadding="0" cellspacing="5" style="background-color: #FFFFFF;">

{foreach item=month from=$year}
	{if $col % 3 == 0}<tr>{/if}
		<td style="vertical-align: top">
            <table name="CRMCalendar" id="Utils_Calendar__year" cellpadding="0" cellspacing="0" border="0">
            	<tr>
            		<td class="header-month" colspan="8"><a {$month.month_link}>{$month.month_label} &bull; {$month.year_label}</a></td>
            	</tr>
            	<tr>
            		<td class="week-number">&nbsp;</td>
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
	</center>
