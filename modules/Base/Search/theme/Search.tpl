{if $form_mini=="yes"}
	{$form_data.javascript}
	<form {$form_data.attributes}>
	{$form_data.hidden}
    <!-- Display the fields -->
	    <div id="Base_Search__Search" style="display: flex; align-items: center;">
				<div class="input">{$form_data.quick_search.html}</div>
				<div class="submit"><div class="search_button_css3_box"><a class="search_button" {$submit_href}>{$submit_label}<div class="search_icon"></div></a></div></div>
		</div>
	</form>
{else}
	{$form_data.javascript}
	<form {$form_data.attributes}>
	{$form_data.hidden}
	    <div id="Base_Search__Search">
	    	<div class="header_tail"><span class="header">{$form_data.header.quick_search_header}</span></div>
			<div><span class="error">{$form_data.quick_search.error}</span></div>
			<div style="display: flex;">
				<div class="label" style="text-align:right;">{$form_data.quick_search.label}</div>
				<div class="data" style="text-align:left;">{$form_data.quick_search.html}</div>
			</div>
			<div class="data" style="text-align:left;"><ul><li>{$form_data.search_categories.html}</li></ul></div>
			<div style="text-align:right;">{$form_data.quick_search_select_none.html}&nbsp;{$form_data.quick_search_select_all.html}&nbsp;{$form_data.quick_search_submit.html}</div>
		</div>
	</form>
{/if}
