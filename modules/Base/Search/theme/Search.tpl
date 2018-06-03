{if $form_mini=="yes"}
	{$form_data.javascript}
	<form {$form_data.attributes}>
	{$form_data.hidden}
    <!-- Display the fields -->
		<div class="input-group" id="search_panel">
			{$form_data.quick_search.html}
			<span class="input-group-append">
                              <button class="btn btn-success" type="button" {$submit_href}><span class="hidden-xs">{$submit_label}&nbsp;</span><i class="fa fa-search"></i></button>
                            </span>
		</div>
		{*<div class="input-group" >*}
			{*{$form_data.quick_search.html}*}
			{*<span class="input-group-btn">*}
				{*<button class="btn btn-success" type="button" {$submit_href}><span class="hidden-xs">{$submit_label}&nbsp;</span><i class="fa fa-search"></i></button>*}
			{*</span>*}
		{*</div>*}
	</form>
{else}
	{$form_data.javascript}
	<form {$form_data.attributes}>
	{$form_data.hidden}
	    <table id="Base_Search__Search">
	    	<tr>
				<td colspan="2" class="header_tail"><span class="header" align="left">{$form_data.header.quick_search_header}</span></td>
			</tr>
			<tr>
				<td colspan="2"><span class="error">{$form_data.quick_search.error}</span></td>
			</tr>
			<tr>
				<td align="right" class="label">{$form_data.quick_search.label}</td>
				<td align="left" class="data">{$form_data.quick_search.html}</td>
			</tr>
			<tr>
				<td colspan="2" align="left" class="data"><ul><li>{$form_data.search_categories.html}</li></ul></td>
			</tr>
			<tr>
				<td colspan="2" align="right">{$form_data.quick_search_select_none.html}&nbsp;{$form_data.quick_search_select_all.html}&nbsp;{$form_data.quick_search_submit.html}</td>
			</tr>
		</table>
	</form>
{/if}
