{if $form_mini == yes}
	<form {$form_data.attributes}>
	<input type=hidden name="_qf__{$form_name}" value="">
	{$form_data.submited.label}
	<input type=hidden name=submited vaalue="0">
    <!-- Display the fields -->
	    <div class=Base_Search>
			{$form_data.quick_search.html}
			{$form_data.quick_search_submit.html}
		</div>
	</form>
{else}
	<form {$form_data.attributes}>
	<input type=hidden name="_qf__{$form_name}" value="">
	{$form_data.submited.label}
	<input type=hidden name=submited vaalue="0">
    <!-- Display the fields -->
	    <table class=Base_Search>
	    	<tr>
				<td colspan=2 class=header_tail>
					<span class=header align=left>{$form_data.header.quick_search_header}</span>
				</td>
			</tr>
			{if $form_data.quick_search.error}
				<tr><td colspan=2><span class=error>{$form_data.quick_search.error}</span></td></tr>
			{/if}
			<tr>
				<td align=right class=label>{$form_data.quick_search.label}</td>
				<td align=left>
					{$form_data.quick_search.html}
				</td>
			</tr>
			<tr><td colspan=2>{$form_data.quick_search_submit.html}</td></tr>
	    	
			{if $form_data.advanced_search_header.label}
				<tr>
					<td colspan=2 class=header_tail>
						<div align=left><span class=header>{$form_data.advanced_search_header.label}</span></div>
					</td>
				</tr>
				<tr>
					<td align=right class=label>{$form_data.advanced_search.label}</td>
					<td align=left>
						{$form_data.advanced_search.html}
					</td>
				</tr>
			{/if}
		</table>
	</form>
{/if}