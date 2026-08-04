</center>
<div class="epesi_caption">
	{$header}
</div>
<div id="shoutbox-content" style="text-align:left;padding:0 10px 0;">
	{$form_open}
		<div style="display: flex; flex-wrap: wrap; align-items: center;">
				<div class="epesi_label" style="width:80px">
					{$form_data.from_date.label}
				</div>
				<div class="epesi_data">
					{$form_data.from_date.html}
				</div>
				<div class="epesi_label" style="width:80px">
					{$form_data.to_date.label}
				</div>
				<div class="epesi_data">
					{$form_data.to_date.html}
				</div>
				<div class="epesi_label" style="width:80px">
					{$form_data.user.label}
				</div>
				<div class="epesi_data">
					{$form_data.user.html}
				</div>
				<div class="epesi_label" style="width:80px">
					{$form_data.search.label}
				</div>
				<div class="epesi_data">
					{$form_data.search.html}
				</div>
				<div class="child_button">
					{$form_data.submit_button.html}
				</div>
		</div>

	{$form_close}
</div>

{$messages}
<center>
