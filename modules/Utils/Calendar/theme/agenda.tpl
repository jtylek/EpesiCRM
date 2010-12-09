{$form_open}

<table style="width:100%">
	<tr>
		<td style="width:25%">
			&nbsp;
		</td>
		<td style="width:50%">
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
		<td style="float:right;">
			{$navigation_bar_additions}
		</td>
	</tr>
</table>

{$form_close}
<br>
{$agenda}
