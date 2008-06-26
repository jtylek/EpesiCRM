{$form_open}
<div style="text-align:left">
<table cellpadding="0" cellspacing="0">
	<tr>
		<td style="text-align:center;font-weight:bold" colspan="6">
			{$form_data.date_range_type.error}
		</td>
	</tr>
	<tr>
		<td>
			{$form_data.date_range_type.label}{$form_data.date_range_type.html}
		</td>
		<td>
			<div id="day_elements">
				<table cellpadding="0" cellspacing="0">
					<tr>
						<td>
							{$form_data.from_day.label}
						</td>
						<td>
							{$form_data.from_day.html}
						</td>
						<td>
							{$form_data.to_day.label}
						</td>
						<td>
							{$form_data.to_day.html}
						<tr>
					<td>
				</table>
			</div>
		</td>
		<td>
			<div id="week_elements">
				{$form_data.from_week.label}{$form_data.from_week.html}
				{$form_data.to_week.label}{$form_data.to_week.html}
			</div>
		</td>
		<td>
			<div id="month_elements">
				{$form_data.from_month.label}{$form_data.from_month.html}
				{$form_data.to_month.label}{$form_data.to_month.html}
			</div>
		</td>
		<td>
			<div id="year_elements">
				{$form_data.from_year.label}{$form_data.from_year.html}
				{$form_data.to_year.label}{$form_data.to_year.html}
			</div>
		</td>
		<td>
			{$form_data.submit.html}
		</td>
	</tr>
</table>
</div>
{$form_close}