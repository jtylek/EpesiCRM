<div class="well well-sm">
	<a class="btn btn-default" {$prevyear_href}>{$prevyear_label}&nbsp;<i class="fa fa-backward" aria-hidden="true"></i></a>
	<a class="btn btn-default" {$today_href}>{$today_label}&nbsp;<i class="fa fa-play fa-rotate-90" aria-hidden="true"></i></a>
	<a class="btn btn-default" {$nextyear_href}><i class="fa fa-forward" aria-hidden="true"></i>&nbsp;{$nextyear_label}</a>
	{$popup_calendar}
	<div class="pull-right">
	{$navigation_bar_additions}
	</div>
</div>


	<div class="layer" style="padding: 9px; width: 764px;">
		<div class="css3_content_shadow">

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
 		</div>
	</div>
