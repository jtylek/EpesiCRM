{if isset($wrong_language)}
	{$form_open}
	<div class="translations_tools_wrong_language_message">{$wrong_language} {$form_data.lang_code.html}</div>
	{$form_close}
{else}

	{$roll_button_up}

	<span id="translations_tools_table_and_utils">
	{$form_open}

	<table width="100%" style="table-layout: auto;">
		<tr>
			<td width="1" nowrap="1">
				{$form_data.lang_code.label}
			</td>
			<td width="1" nowrap="1">
				{$form_data.lang_code.html}
			</td>
			<td width="1" nowrap="1">
				{$form_data.lang_filter.label}
			</td>
			<td width="1" nowrap="1">
				{$form_data.lang_filter.html}
			</td>
			<td width="1" nowrap="1">
				{$form_data.lang_search.label}
			</td>
			<td width="1" nowrap="1">
				{$form_data.lang_search.html}
			</td>
			<td>
			</td>
			<td width="1" nowrap="1">
				{$roll_button_down}
			</td>
			<td width="1" nowrap="1">
				{$report_button}
			</td>
		</tr>
	</table>

	{$form_close}

	{$table}
	</span>

{/if}