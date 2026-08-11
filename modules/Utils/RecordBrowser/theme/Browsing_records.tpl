<div style="text-align: left;">
<div id="Browsing_records" class="nonselectable" style="display: flex; align-items: flex-start;">
	{if isset($caption)}
		<div class="name">
			<img alt=" " class="icon" src="{$icon}" width="32" height="32" border="0">
			<div class="label">
				{if isset($form_data)}
					{$form_open}
				{/if}
				{$caption}
				{if isset($form_data)}
						{$form_data.browse_mode.html}
					{$form_close}
				{/if}
			</div>
		</div>
	{/if}
    <div class="filters" style="flex: 1 1 auto;">
        {if isset($filters.controls)}
            {$filters.controls}
        {/if}
    </div>
</div>
{if isset($filters.elements)}
<div class="filters">
{$filters.elements}
</div>
{/if}
</div>


{$table}
