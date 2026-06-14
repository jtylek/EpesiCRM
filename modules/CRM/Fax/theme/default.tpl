{$form_open}

<table id="CRM_Fax__filters" align="left" width="100%;">
	<tr>
		{if isset($form_data.status)}
		<td class="label">
			{$form_data.status.label}
		</td>
		<td class="data" width="30px;">
			{$form_data.status.error}
			{$form_data.status.html}
		</td>
		{/if}
		{if isset($form_data.start)}
		<td class="label">
			{$form_data.start.label}
		</td>
		<td class="data" width="30px;">
			{$form_data.start.error}
			{$form_data.start.html}
		</td>
		<td class="label">
			{$form_data.end.label}
		</td>
		<td class="data" width="30px;">
			{$form_data.end.error}
			{$form_data.end.html}
		</td>
		{/if}
		<td class="data" width="30px;">
			{$form_data.submit_button.html}
		</td>
	</tr>
</table>
		

{$form_close}

<br><br>
{$table_data}