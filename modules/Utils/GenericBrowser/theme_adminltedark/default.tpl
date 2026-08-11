{* The {html_grid_epesi} call below keeps table_id/cols_width_id/class="Utils_GenericBrowser"
   and the fixed table-layout style byte-for-byte identical to the default theme - js/table_overflow.js
   keys off exactly these, not the surrounding chrome, which is all this theme actually changes.
   js/col_resizable.js's drag-to-resize-columns feature was dropped entirely when this grid moved
   from a real <table> to CSS table-display divs (see AI-shared/adminlte-theme.md) - the vendored
   plugin hard-requires an actual <table> element and has no div-compatible equivalent. *}

<div class="epesi-gb card mb-3">

{if (isset($custom_label) && $custom_label) || isset($form_data_search) || isset($expand_collapse)}
<div class="card-header epesi-gb-toolbar d-flex flex-wrap align-items-center gap-2">
	{if (isset($custom_label) && $custom_label)}
		<span class="epesi-gb-label" {$custom_label_args}>{$custom_label}</span>
	{/if}

	{if isset($expand_collapse)}
		<div class="btn-group btn-group-sm" role="group">
			<a id="{$expand_collapse.e_id}" class="btn epesi-gb-expand-btn" {$expand_collapse.e_href}><i class="bi bi-arrows-expand"></i> {$expand_collapse.e_label}</a>
			<a id="{$expand_collapse.c_id}" class="btn epesi-gb-expand-btn" {$expand_collapse.c_href}><i class="bi bi-arrows-collapse"></i> {$expand_collapse.c_label}</a>
		</div>
	{/if}

	{if isset($form_data_search)}
		<div class="epesi-gb-search ms-md-auto">
			{$form_data_search.javascript}
			<form {$form_data_search.attributes} class="d-flex flex-wrap align-items-center gap-2">
			{$form_data_search.hidden}
			{if isset($search_fields_hidden)}{$search_fields_hidden}{/if}
			{if isset($form_data_search.search)}
				<span class="epesi-gb-adv">{$adv_search}</span>
				{$form_data_search.search.html}
				{$form_data_search.submit_search.html}
				{if isset($form_data_search.show_all)}
					{$form_data_search.show_all.html}
				{/if}
			{else}
				{php}
					$cols = $this->get_template_vars('cols');
					$search_fields = $this->get_template_vars('search_fields');
					foreach($cols as $k=>$v){
						if(isset($search_fields[$k]))
							$cols[$k]['label'] = $cols[$k]['label'].$search_fields[$k];
					}
					$this->assign('cols',$cols);
				{/php}
				{if isset($form_data_search.submit_search)}
					<span class="epesi-gb-adv">{$adv_search}</span>
					{$form_data_search.submit_search.html}
					{if isset($form_data_search.show_all)}
						{$form_data_search.show_all.html}
					{/if}
				{/if}
			{/if}
			</form>
		</div>
	{/if}
</div>
{/if}

{php}
	$cols = $this->get_template_vars('cols');
	foreach($cols as $k=>$v)
		$cols[$k]['label'] = '<span>'.$cols[$k]['label'].'</span>';
	$this->assign('cols',$cols);
	// Mobile 2-line-per-row layout (see this theme's default.css "mobile:
	// 2-line-per-row layout" block) needs a per-table column-per-line count -
	// column count varies per module, so it can't be a fixed CSS number.
	// Computed here rather than in GenericBrowser_0.php since it's purely a
	// presentation concern of this theme.
	// Excludes RecordBrowser's own favourite/watchdog columns
	// (RecordBrowser_0.php's fixed_columns_class, tagged via their own
	// 'attrs' class) - default.css's existing 991.98px breakpoint already
	// display:none's those columns (which also covers this 767.98px one),
	// so counting them here overstated the per-line total and left trailing
	// empty grid cells on every row instead of an even 2-line split.
	$mobile_col_count = 0;
	foreach ($cols as $v) {
		if (preg_match('/class="[^"]*\bUtils_RecordBrowser__(?:favs|watchdog)\b/', $v['attrs'] ?? '')) continue;
		$mobile_col_count++;
	}
	$this->assign('mobile_cols', max(1, (int)ceil($mobile_col_count/2)));
{/php}

<div class="card-body p-0">
	<div class="table-responsive">
		{$table_prefix}
		{capture name="table_attr"}id="{$table_id}" cols_width_id="{$cols_width_id}" class="Utils_GenericBrowser table table-hover align-middle mb-0" style="width:100%;table-layout:fixed;overflow:hidden;text-overflow:ellipsis;--epesi-gb-mobile-cols:{$mobile_cols};"{/capture}
		{html_grid_epesi table_attr=$smarty.capture.table_attr loop=$data cols=$cols row_attrs=$row_attrs}
		{$table_postfix}
	</div>
</div>

{if isset($form_data_paging)}
{$form_data_paging.javascript}

<form {$form_data_paging.attributes}>
{$form_data_paging.hidden}
{/if}
{if isset($order) || $first || $prev || $summary || isset($form_data_paging.page) || isset($form_data_paging.per_page)}
	<div class="card-footer epesi-gb-nav d-flex flex-wrap align-items-center gap-2">
		{if isset($order)}
			<div class="epesi-gb-order small text-muted">{$order} <strong>{$reset}</strong></div>
		{/if}

		{if isset($__link.first.open) || isset($__link.last.open)}
			<div class="epesi-gb-pager btn-group btn-group-sm">
				{if isset($__link.first.open)}{$__link.first.open}<i class="bi bi-chevron-bar-left"></i> {$__link.first.text}{$__link.first.close}{/if}
				{if isset($__link.prev.open)}{$__link.prev.open}<i class="bi bi-chevron-left"></i> {$__link.prev.text}{$__link.prev.close}{/if}
			</div>
		{/if}

		<div class="epesi-gb-summary small text-muted text-center flex-grow-1">{$summary}</div>

		{if isset($__link.first.open) || isset($__link.last.open)}
			<div class="epesi-gb-pager btn-group btn-group-sm">
				{if isset($__link.next.open)}{$__link.next.open}{$__link.next.text} <i class="bi bi-chevron-right"></i>{$__link.next.close}{/if}
				{if isset($__link.last.open)}{$__link.last.open}{$__link.last.text} <i class="bi bi-chevron-bar-right"></i>{$__link.last.close}{/if}
			</div>
		{/if}

		{if isset($form_data_paging.page)}
			<div class="epesi-gb-page d-flex align-items-center gap-1 small ms-md-auto">{$form_data_paging.page.label} {$form_data_paging.page.html}</div>
		{/if}
		{if isset($form_data_paging.per_page)}
			<div class="epesi-gb-per-page d-flex align-items-center gap-1 small">{$form_data_paging.per_page.label} {$form_data_paging.per_page.html}</div>
		{/if}
	</div>
{/if}
{if isset($form_data_paging)}
</form>
{/if}

</div>
