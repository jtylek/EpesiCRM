{* Was a <table> (field/old-value/new-value columns, a genuine 2D data
   grid) - CSS Grid instead; role="table"/"row"/"columnheader"/"cell"
   restore ARIA table semantics. Each row is a display:contents wrapper so
   its cells still lay out directly against the grid's own column tracks -
   the .header/.last_row presentation classes moved from the row onto its
   cells, since display:contents elements paint no box of their own (see
   AI-shared/adminlte-theme.md). *}
<div class="Utils_RB__changelist" role="table" style="display: grid; grid-template-columns: 30% 35% 35%;">
	{if isset($header)}
		<div role="row" style="display: contents;">
			<div class="header" role="columnheader">
				{$header.0}
			</div>
			<div class="header" role="columnheader">
				{$header.1}
			</div>
			<div class="header" role="columnheader">
				{$header.2}
			</div>
		</div>
	{/if}
	{foreach from=$events item=e}
		{if is_string($e.what)}
			<div role="row" style="display: contents;">
				<div class="message{if !isset($e.who)} last_row{/if}" role="cell" style="grid-column: 1 / -1;">
					{$e.what}
				</div>
			</div>
		{else}
			{foreach from=$e.what item=r}
				<div role="row" style="display: contents;">
					<div class="field{if !isset($e.who)} last_row{/if}" role="cell">
						{$r.0}
					</div>
					<div class="data{if !isset($e.who)} last_row{/if}" role="cell">
						{$r.1}
					</div>
					<div class="data{if !isset($e.who)} last_row{/if}" role="cell">
						{$r.2}
					</div>
				</div>
			{/foreach}
		{/if}
		{if isset($e.who)}
			<div role="row" style="display: contents;">
				<div class="user last_row" role="cell" style="grid-column: span 2;">
					{$e.who}
				</div>
				<div class="timestamp last_row" role="cell">
					{$e.when}
				</div>
			</div>
		{/if}
	{/foreach}
</div>
