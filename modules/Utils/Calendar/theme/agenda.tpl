{$form_open}

<table style="width:98%">
	<tr>
		<td>
		</td>
		<td style="width:400px;">
			<table id="Utils_Calendar__agenda" border="0" cellspacing="0" cellpadding="0">
				<tbody>
					<tr>
						<td class="label">{$form_data.start.label}</td><td class="data">{$form_data.start.html}</td>
						<td>&nbsp;&nbsp;</td>
						<td class="label">{$form_data.end.label}</td><td class="data">{$form_data.end.html}</td>
						<td>&nbsp;&nbsp;</td>
						<td class="button">{$form_data.submit_button.html}</td>
					</tr>
				</tbody>
			</table>
		</td>
		<td>
		</td>
		<td class="button_cell">
			{$navigation_bar_additions}
		</td>
	</tr>
</table>

{$form_close}
<br>
{$agenda}
