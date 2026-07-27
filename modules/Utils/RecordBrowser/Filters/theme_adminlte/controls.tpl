{* filters.js (Utils_RecordBrowser_Filters.show()/hide()) only toggles these
   by id via jQuery .show()/.hide() - no dependency on the <input>/<button>
   tag or a .value attribute, so switching to <button> with text content is
   safe. *}
<div id="Utils_RecordBrowser__Filter" class="epesi-rbf-controls">
	<button type="button" class="epesi-rbf-btn" {if $filter_group.visible}style="display: none;"{/if} {$filter_group.show.attrs}>
		<i class="bi bi-funnel"></i> {$filter_group.show.label}
	</button>
	<button type="button" class="epesi-rbf-btn" {if !$filter_group.visible}style="display: none;"{/if} {$filter_group.hide.attrs}>
		<i class="bi bi-funnel-fill"></i> {$filter_group.hide.label}
	</button>
</div>
