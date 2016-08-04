<div class="panel panel-default">
	{if isset($caption)}
			<div class="panel-heading clearfix">
				<img alt=" " class="icon pull-left" src="{$icon}" width="32" height="32" border="0">
				<div class="pull-left form-inline" style="margin-top: 5px">
					{if isset($form_data)}
						{$form_open}
					{/if}
					<span>{$caption}</span>
					{if isset($form_data)}
						{$form_data.browse_mode.html}
						{$form_close}
					{/if}
				</div>
                {if $filters.controls}
                    {$filters.controls}
                {/if}
			</div>
	{/if}
	<div class="panel-body">
        {if $filters.elements}
		    <div>{$filters.elements}</div>
        {/if}
		{$table}
	</div>
</div>