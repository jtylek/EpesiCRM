{$form_open}

<div style="display: flex; width:98%;">
		<div style="flex: 1 1 auto;">
		</div>
		<div style="width:400px;">
			<div id="Utils_Calendar__agenda" style="display: flex; align-items: center;">
					<div class="epesi_label">{$form_data.start.label}</div><div class="epesi_data">{$form_data.start.html}</div>
					<div>&nbsp;&nbsp;</div>
					<div class="epesi_label">{$form_data.end.label}</div><div class="epesi_data">{$form_data.end.html}</div>
					<div>&nbsp;&nbsp;</div>
					<div class="child_button">{$form_data.submit_button.html}</div>
			</div>
		</div>
		<div style="flex: 1 1 auto;">
		</div>
		<div class="button_cell">
			{$navigation_bar_additions}
		</div>
</div>

{$form_close}
<br>
{$agenda}
