{*		{php}
		print_r($this->_tpl_vars['form_data']);
		{/php}*}

{$form_open}
<div style="text-align:left; padding-left: 10px; padding-right: 10px;">

<table cellpadding="0" cellspacing="0" class="Utils_RecordBrowser_Reports__date_picker">
	<tr>
		{assign var=block value=0}
		{foreach item=e key=k from=$form_data}
			{if is_array($e) && isset($e.name) && $e.name=="date_range_type"}
				{assign var=block value=1}
			{/if}
			{if $block==0 && is_array($e) && isset($e.html)}
				<td class="label">
					{$e.label}
				</td>
				<td class="data">
					{$e.html}
				</td>
			{/if}
		{/foreach}
	</tr>
</table>
<table cellpadding="0" cellspacing="0" class="Utils_RecordBrowser_Reports__date_picker">
	<tr>
		<td style="text-align:center;font-weight:bold" colspan="7">
			{$form_data.date_range_type.error}
		</td>
	</tr>
	<tr>
		<td class="label">
			{$form_data.date_range_type.label}
		</td>
		<td class="data">
			{$form_data.date_range_type.html}
		</td>
		<td>
			<div id="day_elements">
				<table cellpadding="0" cellspacing="0" class="Utils_RecordBrowser_Reports__date_picker">
					<tr>
						<td class="label">
							{$form_data.from_day.label}
						</td>
						<td class="data">
							{$form_data.from_day.html}
						</td>
						<td class="label">
							{$form_data.to_day.label}
						</td>
						<td class="data">
							{$form_data.to_day.html}
						<tr>
					<td>
				</table>
			</div>
		</td>
		<td>
			<div id="week_elements">
				<table cellpadding="0" cellspacing="0" class="Utils_RecordBrowser_Reports__date_picker">
					<tbody>
						<tr>
							<td class="label">{$form_data.from_week.label}</td><td class="data">{$form_data.from_week.html}</td>
							<td class="label">{$form_data.to_week.label}</td><td class="data">{$form_data.to_week.html}</td>
						</tr>
					</tbody>
				</table>
			</div>
		</td>
		<td>
			<div id="month_elements">
				<table cellpadding="0" cellspacing="0" class="Utils_RecordBrowser_Reports__date_picker">
					<tbody>
						<tr>
							<td class="label">{$form_data.from_month.label}</td><td class="data">{$form_data.from_month.html}</td>
							<td class="label">{$form_data.to_month.label}</td><td class="data">{$form_data.to_month.html}</td>
						</tr>
					</tbody>
				</table>
			</div>
		</td>
		<td>
			<div id="year_elements">
				<table cellpadding="0" cellspacing="0" class="Utils_RecordBrowser_Reports__date_picker">
					<tbody>
						<tr>
							<td class="label">{$form_data.from_year.label}</td><td class="data">{$form_data.from_year.html}</td>
							<td class="label">{$form_data.to_year.label}</td><td class="data">{$form_data.to_year.html}</td>
						</tr>
					</tbody>
				</table>
			</div>
		</td>
		<td class="button">
			{$form_data.submit.html}
		</td>
	</tr>
</table>
</div>
{$form_close}
