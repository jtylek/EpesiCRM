<div class="card">
	{if isset($caption)}
		<div class="card-header clearfix">
			<i class="fa fa-{$icon} fa-2x pull-left" style="padding-top: 7px"></i>
			{*<img alt=" " class="icon pull-left" src="{$icon}" width="32" height="32" border="0">*}
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
			{if $filters.elements}
				{$filters.elements}
			{/if}
		</div>
	{/if}

	{$table}
</div>
