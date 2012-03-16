</center>
<div class="epesi_caption">
	{$header}
</div>
<div id="shoutbox-content" style="text-align:left;padding:0 10px 0;">
	{$form_open}
		<table>
			<tr>
				<td class="epesi_label" style="width:80px">
					{$form_data.from_date.label}
				</td>
				<td class="epesi_data">
					{$form_data.from_date.html}
				</td>
				<td class="epesi_label" style="width:80px">
					{$form_data.to_date.label}
				</td>
				<td class="epesi_data">
					{$form_data.to_date.html}
				</td>
				<td class="epesi_label" style="width:80px">
					{$form_data.user.label}
				</td>
				<td class="epesi_data">
					{$form_data.user.html}
				</td>
				<td class="epesi_label" style="width:80px">
					{$form_data.search.label}
				</td>
				<td class="epesi_data">
					{$form_data.search.html}
				</td>
				<td class="child_button">
					{$form_data.submit_button.html}
				</td>
			</tr>
		</table>

	{$form_close}
</div>

{$messages}
<center>