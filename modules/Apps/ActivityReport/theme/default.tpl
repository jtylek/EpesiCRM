{$form_open}

<div class="epesi_grey_board" style="width:850px;">
<table id="Apps_ActivityReport">
	<tr>
		<td class="epesi_label top" style="width:400px;">
			{$form_data.recordsets.label}
		</td>
		<td class="epesi_label" style="width:150px;">
			{$form_data.user.label}
		</td>
		<td class="epesi_data" colspan="3" style="width:250px;">
			{$form_data.user.html}
		</td>
	</tr>
	<tr>
		<td class="epesi_data multiselect" rowspan="5">
			{$form_data.recordsets.html}
		</td>
		<td class="epesi_label">
			{$form_data.start_date.label}
		</td>
		<td class="epesi_data" colspan="3">
			{$form_data.start_date.html}
		</td>
	</tr>
	<tr>
		<td class="epesi_label">
			{$form_data.end_date.label}
		</td>
		<td class="epesi_data" colspan="3">
			{$form_data.end_date.html}
		</td>
	</tr>
	<tr>
		<td class="epesi_label">
			{$form_data.new.label}
		</td>
		<td class="epesi_data">
			{$form_data.new.html}
		</td>
		<td class="epesi_label">
			{$form_data.note.label}
		</td>
		<td class="epesi_data">
			{$form_data.note.html}
		</td>
	</tr>
	<tr>
		<td class="epesi_label">
			{$form_data.edit.label}
		</td>
		<td class="epesi_data">
			{$form_data.edit.html}
		</td>
		<td class="epesi_label">
			{$form_data.file.label}
		</td>
		<td class="epesi_data">
			{$form_data.file.html}
		</td>
	</tr>
	<tr>
		<td class="epesi_label">
			{$form_data.delete_restore.label}
		</td>
		<td class="epesi_data">
			{$form_data.delete_restore.html}
		</td>
		<td colspan="2">
		</td>
	</tr>
</table>
</div>
<br>
{$form_close}
