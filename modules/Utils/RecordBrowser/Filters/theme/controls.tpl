<div id="Utils_RecordBrowser__Filter">
	<div class="buttons">
		<input type="button" {if $filter_group.visible}style="display: none;"{/if} {$filter_group.show.attrs} value="{$filter_group.show.label}">
		<input type="button" {if !$filter_group.visible}style="display: none;"{/if} {$filter_group.hide.attrs} value="{$filter_group.hide.label}">
	</div>
</div>
