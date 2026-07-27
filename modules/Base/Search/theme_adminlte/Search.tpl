{php}
	// Search_0.php doesn't set a placeholder on the quick_search field (shared
	// with the default theme, which has its own visible "Quick Search" label
	// instead) - injected here, theme-scoped, rather than in the shared form
	// definition.
	$form_data = $this->get_template_vars('form_data');
	if (isset($form_data['quick_search']['html']) && !str_contains($form_data['quick_search']['html'], 'placeholder=')) {
		$placeholder = htmlspecialchars(__('Search...'), ENT_QUOTES);
		$form_data['quick_search']['html'] = preg_replace('/<input\b/i', '<input placeholder="' . $placeholder . '"', $form_data['quick_search']['html'], 1);
	}
	$this->assign('form_data', $form_data);
{/php}
{if $form_mini=="yes"}
	{$form_data.javascript}
	<form {$form_data.attributes} class="epesi-search-mini">
	{$form_data.hidden}
		<div class="epesi-search-mini-group">
			{$form_data.quick_search.html}
			<a class="epesi-search-btn" {$submit_href} title="{$submit_label}"><i class="bi bi-search"></i></a>
		</div>
	</form>
{else}
	{$form_data.javascript}
	<form {$form_data.attributes} class="epesi-search-full card">
	{$form_data.hidden}
		<div class="card-body">
			<div class="epesi-search-full-header"><i class="bi bi-search"></i> {$form_data.header.quick_search_header}</div>
			<div class="text-danger small">{$form_data.quick_search.error}</div>
			<div class="mb-3">
				<label class="form-label small mb-1">{$form_data.quick_search.label}</label>
				{$form_data.quick_search.html}
			</div>
			<ul class="epesi-search-categories list-unstyled">
				<li>{$form_data.search_categories.html}</li>
			</ul>
			<div class="epesi-search-full-actions d-flex justify-content-end gap-2 mt-3">
				{$form_data.quick_search_select_none.html}
				{$form_data.quick_search_select_all.html}
				{$form_data.quick_search_submit.html}
			</div>
		</div>
	</form>
{/if}
