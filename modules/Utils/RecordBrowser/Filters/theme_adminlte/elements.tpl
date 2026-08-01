{* Was a <table> hand-wrapping every 4 label/data pairs into a new <tr>
   (the default theme's own layout math, kept only as long as this stayed a
   table). flex-wrap replaces that: each .epesi-rbf-pair is a fixed-content
   flex item, and the container just wraps them onto as many per row as
   actually fit - no more hardcoded "4", and no x/first counter bookkeeping
   needed at all. *}
{$form_open}

<div id="recordbrowser_filters_{$filter_group.id}" class="Utils_RecordBrowser__Filter" {if !$filter_group.visible}style="display: none;"{/if}>
	<div class="epesi-rbf-row">
		{foreach item=f from=$filter_group.elements}
			<div class="epesi-rbf-pair">
				<div class="label">{$form_data.$f.label}</div>
				<div class="data">{$form_data.$f.html}</div>
			</div>
		{/foreach}
		<div class="buttons">{$form_data.submit.html}</div>
	</div>
</div>

{$form_close}
