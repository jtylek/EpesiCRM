
	<div class="pull-right">
		<input class="btn btn-success" type="button" {if $filter_group.visible}style="display: none;"{/if} {$filter_group.show.attrs} value="{$filter_group.show.label}">
		<input class="btn btn-danger" type="button" {if !$filter_group.visible}style="display: none;"{/if} {$filter_group.hide.attrs} value="{$filter_group.hide.label}">
	</div>