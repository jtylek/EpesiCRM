{if $form_mini=="yes"}
	{$form_data.javascript}
	<form {$form_data.attributes}>
	{$form_data.hidden}
    <!-- Display the fields -->
	    <table id="Base_Search__Search" cellpadding="0" cellspacing="0" border="0">
			<tr>
				<td class="input">{$form_data.quick_search.html}</td>
				<td class="submit"><div class="search_button_css3_box"><a class="search_button" {$submit_href}>{$submit_label}<div class="search_icon"></div></a></div></td>
			</tr>		
		</table>
	</form>
{else}
	{$form_data.javascript}
	<form {$form_data.attributes}>
	{$form_data.hidden}
    <!-- Display the fields -->
    <table>
    <tr>
    <td style="vertical-align: top;">
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
				<td colspan="2" align="right">{$form_data.quick_search_submit.html}</td>
			</tr>
		</table>
	</td>
	<td style="vertical-align: top;">	
		<table id="Base_Search__Search">
			{if isset($form_data.advanced_search_header)}
			<tr>
				<td colspan="2" class="header_tail"><span class="header">{$form_data.advanced_search_header.label}</span></td>
			</tr>
			<tr>
				<td align="right" class="label">{$form_data.advanced_search.label}</td>
				<td align="left" class="data">{$form_data.advanced_search.html}</td>
			</tr>
			{/if}
		</table>
	</td>
	</tr>
	</table>	
	</form>
{/if}