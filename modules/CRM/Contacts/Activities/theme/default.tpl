{$form_open}

<div id="CRM_Contacts_Activities" style="display: flex; width: 100%;">
	<div style="width: 50%;">
		<div style="display: flex; flex-wrap: wrap; align-items: center;">
			<div class="epesi_label">
				{$form_data.header.display}
			</div>
			<div class="epesi_label">
				{$form_data.events.label}
			</div>
			<div class="epesi_data" style="width:25px;">
				{$form_data.events.html}
			</div>
			<div class="epesi_label">
				{$form_data.tasks.label}
			</div>
			<div class="epesi_data" style="width:25px;">
				{$form_data.tasks.html}
			</div>
			<div class="epesi_label">
				{$form_data.phonecalls.label}
			</div>
			<div class="epesi_data" style="width:25px;">
				{$form_data.phonecalls.html}
			</div>
			<div class="epesi_label">
				{$form_data.closed.label}
			</div>
			<div class="epesi_data" style="width:25px;">
				{$form_data.closed.html}
			</div>
			<div class="epesi_label">
				{$form_data.activities_date.label}
			</div>
			<div class="epesi_data">
				{$form_data.activities_date.html}
			</div>
		</div>
	</div>
	<div class="actions" style="flex: 1 1 auto;">
	</div>
</div>


{$form_close}
