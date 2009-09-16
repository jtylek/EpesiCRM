{$form_open}
	<table>
		{foreach item=e from=$elements_name}
			<tr>
				<td nowrap="1">
					{$form_data.$e.label}
				</td>
				<td>
					{$form_data.$e.html}
				</td>
			</tr>
		{/foreach}
	</table>
{$form_close}