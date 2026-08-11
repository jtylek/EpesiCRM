<div id="Browsing_records" style="display: flex; align-items: flex-start;">
	{if $select_form != ""}
	<div style="width: 200px;">
		{$select_form}
	</div>
	{/if}
	<div>
		<div id="Utils_RecordBrowser__Filter">
			<div class="buttons" style="float:left;">
			{if $select_all.js!=""}
				<input type="button" onClick="{$select_all.js}" value="{$select_all.label}">
		    {/if}
			{if $deselect_all.js!=""}
				<input type="button" onClick="{$deselect_all.js}" value="{$deselect_all.label}">
			{/if}
			{if isset($close_leightbox)}
				<input type="button" onClick="{$close_leightbox.js}" value="{$close_leightbox.label}">
			{/if}
			</div>
		</div>
	</div>
	<div class="filters" style="flex: 1 1 auto;">
        {if $filters.controls}
            {$filters.controls}
        {/if}
	</div>
</div>
{if $filters.elements}
<div class="filters">
{$filters.elements}
</div>
{/if}
{$table}