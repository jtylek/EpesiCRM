{if $no_comments}
	{$no_comments}<br>
{else}
	{foreach item=c from=$comments}
	{* Was a <table> with a <td rowspan="2"> indent spacer and a
	   <td rowspan="2"> user column beside a date/action row, with a
	   contents row below manually width-matched via colspan math
	   ($c.tabs*-40+400) to land under just the date+action columns -
	   CSS Grid instead: grid-row:span 2 for the rowspanned indent/user
	   cells, grid-column:span 2 for the contents cell, no manual width
	   arithmetic needed since the grid's own column tracks already know
	   how wide the date+action columns are (see AI-shared/adminlte-theme.md). *}
	<div id="Utils_Comment__Comment" role="table" style="display: grid; grid-template-columns: {$c.tabs*40}px 100px minmax(0, 1fr) 200px;">
		<div class="indent" role="presentation" style="grid-row: span 2;"></div>
		<div class="user" role="cell" style="grid-row: span 2;">{$c.user}</div>
		<div class="date" role="cell">{$c.date}</div>
		<div class="action" role="cell">{$c.reply}&nbsp;{$c.delete}&nbsp;{$c.report}</div>
		<div class="contents" role="cell" style="grid-column: span 2;">{$c.text}</div>
	</div>
	{/foreach}

	<div style="display: flex;">
		<div style="width: 245px; text-align: right;">
			{$first}&nbsp;{$prev}
		</div>
		<div style="width: 55px; text-align: right;">
			{$next}&nbsp;{$last}
		</div>
		<div style="width: 190px; text-align: right;">
			{foreach item=text from=$pages}{$text}&nbsp;{/foreach}
		</div>
	</div>
{/if}
