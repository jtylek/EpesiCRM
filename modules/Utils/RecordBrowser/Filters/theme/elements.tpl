{$form_open}

<div id="recordbrowser_filters_{$filter_group.id}" class="Utils_RecordBrowser__Filter form-inline " style="margin-bottom: 20px; {if !$filter_group.visible}display: none;{/if}">
	{foreach item=f from=$filter_group.elements}
		<div class="input-group">
			<span class="input-group-addon">{$form_data.$f.label}</span>
			{$form_data.$f.html}
		</div>
	{/foreach}
	{$form_data.submit.html}

	{$form_close}
</div>