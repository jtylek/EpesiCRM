<div class="form-inline pull-left" style="margin-bottom: 20px;">
	{$form_open}
	{$form_data.header.display}
	<div class="input-group">
		<span class="input-group-addon">{$form_data.events.label}</span>
		<span class="form-control">{$form_data.events.html}</span>
	</div>
	<div class="input-group">
		<span class="input-group-addon">{$form_data.tasks.label}</span>
		<span class="form-control">{$form_data.tasks.html}</span>
	</div>
	<div class="input-group">
		<span class="input-group-addon">{$form_data.phonecalls.label}</span>
		<span class="form-control">{$form_data.phonecalls.html}</span>
	</div>
	<div class="input-group">
		<span class="input-group-addon">{$form_data.closed.label}</span>
		<span class="form-control">{$form_data.closed.html}</span>
	</div>
	<div class="input-group">
		<span class="input-group-addon">{$form_data.activities_date.label}</span>
		{$form_data.activities_date.html}
	</div>
	{$form_close}
</div>