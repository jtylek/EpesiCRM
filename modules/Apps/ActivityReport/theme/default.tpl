{$form_open}

<table id="Apps_ActivityReport">
	<tr>
		<td class="label">
			{$form_data.recordsets.label}
		</td>
		<td class="label">
			{$form_data.user.label}
		</td>
		<td class="data" colspan="3">
			{$form_data.user.html}
		</td>
	</tr>
	<tr>
		<td class="data" rowspan="7">
			{$form_data.recordsets.html}
		</td>
		<td class="label">
			{$form_data.start_date.label}
		</td>
		<td class="data" colspan="3">
			{$form_data.start_date.html}
		</td>
	</tr>
	<tr>
		<td class="label">
			{$form_data.end_date.label}
		</td>
		<td class="data" colspan="3">
			{$form_data.end_date.html}
		</td>
	</tr>
	<tr>
		<td class="label">
			{$form_data.new.label}
		</td>
		<td class="data">
			{$form_data.new.html}
		</td>
		<td class="label">
			{$form_data.note.label}
		</td>
		<td class="data">
			{$form_data.note.html}
		</td>
	</tr>
	<tr>
		<td class="label">
			{$form_data.edit.label}
		</td>
		<td class="data">
			{$form_data.edit.html}
		</td>
		<td class="label">
			{$form_data.file.label}
		</td>
		<td class="data">
			{$form_data.file.html}
		</td>
	</tr>
	<tr>
		<td class="label">
			{$form_data.delete_restore.label}
		</td>
		<td class="data">
			{$form_data.delete_restore.html}
		</td>
		<td colspan="2">
		</td>
	</tr>
	<tr>
		<td colspan="4" class="activity_report_button">
			<center>
				{$form_data.submit.html}
			</center>
		</td>
	</tr>
</table>

{$form_close}
