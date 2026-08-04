{*		{php}
		print_r($this->_tpl_vars['form_data']);
		{/php}*}

{$form_open}
<div style="text-align:left; padding-left: 10px; padding-right: 10px;">

<div class="Utils_RecordBrowser_Reports__date_picker" style="display: flex; flex-wrap: wrap; align-items: flex-start;">
	{assign var=block value=0}
	{foreach item=e key=k from=$form_data}
		{if is_array($e) && isset($e.name) && ($e.name=="date_range_type" || $e.name=="submit" )}
			{assign var=block value=1}
		{/if}
		{if $block==0 && is_array($e) && isset($e.html)}
			<div class="epesi_label">
				{$e.label}
			</div>
			<div {if is_array($e.value)}style="width:314px" {/if}class="epesi_data">
				{$e.html}
			</div>
		{/if}
	{/foreach}
{if $show_dates}
</div>
<div class="Utils_RecordBrowser_Reports__date_picker">
	<div style="text-align:center;font-weight:bold">
		{$form_data.date_range_type.error}
	</div>
</div>
<div class="Utils_RecordBrowser_Reports__date_picker" style="display: flex; flex-wrap: wrap; align-items: flex-start;">
	<div class="epesi_label">
		{$form_data.date_range_type.label}
	</div>
	<div class="epesi_data">
		{$form_data.date_range_type.html}
	</div>
	<div>
		{* Was a mini <table> whose only row ended with a stray, never-closed
		   <tr><td> pair (likely a typo for a closing </td></tr>) - dropped
		   while converting, not reproduced (see AI-shared/adminlte-theme.md). *}
		<div id="day_elements" class="Utils_RecordBrowser_Reports__date_picker" style="display: flex; flex-wrap: wrap;">
			<div class="epesi_label">
				{$form_data.from_day.label}
			</div>
			<div class="epesi_data">
				{$form_data.from_day.html}
			</div>
			<div class="epesi_label">
				{$form_data.to_day.label}
			</div>
			<div class="epesi_data">
				{$form_data.to_day.html}
			</div>
		</div>
	</div>
	<div>
		<div id="week_elements" class="Utils_RecordBrowser_Reports__date_picker" style="display: flex; flex-wrap: wrap;">
			<div class="epesi_label">{$form_data.from_week.label}</div><div class="epesi_data">{$form_data.from_week.html}</div>
			<div class="epesi_label">{$form_data.to_week.label}</div><div class="epesi_data">{$form_data.to_week.html}</div>
		</div>
	</div>
	<div>
		<div id="month_elements" class="Utils_RecordBrowser_Reports__date_picker" style="display: flex; flex-wrap: wrap;">
			<div class="epesi_label">{$form_data.from_month.label}</div><div class="epesi_data">{$form_data.from_month.html}</div>
			<div class="epesi_label">{$form_data.to_month.label}</div><div class="epesi_data">{$form_data.to_month.html}</div>
		</div>
	</div>
	<div>
		<div id="year_elements" class="Utils_RecordBrowser_Reports__date_picker" style="display: flex; flex-wrap: wrap;">
			<div class="epesi_label">{$form_data.from_year.label}</div><div class="epesi_data" style="width:auto;">{$form_data.from_year.html}</div>
			<div class="epesi_label">{$form_data.to_year.label}</div><div class="epesi_data" style="width:auto;">{$form_data.to_year.html}</div>
		</div>
	</div>
{/if}
	<div class="child_button">
		{$form_data.submit.html}
	</div>
</div>
</div>
{$form_close}
<br>
