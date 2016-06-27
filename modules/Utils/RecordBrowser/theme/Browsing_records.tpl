<div class="panel panel-default">
	{if isset($caption)}
			<div class="panel-heading clearfix">
				<img alt=" " class="icon pull-left" src="{$icon}" width="32" height="32" border="0">
				<div class="pull-left" style="margin-top: 5px">
					{if isset($form_data)}
						{$form_open}
					{/if}
					<span>{$caption}</span>
					{if isset($form_data)}
						{$form_data.browse_mode.html}
						{$form_close}
					{/if}
				</div>
				{if $filters}
						<div class="pull-right">
							<input class="btn btn-success" type="button" {if $filters.dont_hide}style="display: none;"{/if} {$filters.show_filters.attrs} value="{$filters.show_filters.label}">
							<input class="btn btn-danger" type="button" {if !$filters.dont_hide}style="display: none;"{/if} {$filters.hide_filters.attrs} value="{$filters.hide_filters.label}">
						</div>
				{/if}
			</div>
	{/if}
	<div class="panel-body">
		<div>{$filters.filter_form}</div>
		{$table}
	</div>
</div>