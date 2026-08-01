{* AdminLTE re-skin of the filter form above the activity report's results
   table (ActivityReport_0.php::body() - the GenericBrowser results table
   below this form is themed generically, nothing table-specific needed
   here). Field markup itself ($form_data.*.html) is QuickForm-generated,
   shared with the default theme - only the surrounding layout/labels
   change. "recordsets" is the same multiselect dual-listbox widget already
   styled globally in Libs/QuickForm/theme_adminlte/default.css. *}
{$form_open}

<div class="card mb-3 epesi-ar">
	<div class="card-body">
		<div class="row g-3">
			<div class="col-md-5">
				<label class="form-label fw-semibold">{$form_data.recordsets.label}</label>
				{$form_data.recordsets.html}
			</div>
			<div class="col-md-7">
				<div class="row g-3">
					<div class="col-sm-6">
						<label class="form-label">{$form_data.start_date.label}</label>
						{$form_data.start_date.html}
					</div>
					<div class="col-sm-6">
						<label class="form-label">{$form_data.end_date.label}</label>
						{$form_data.end_date.html}
					</div>
				</div>
				<div class="row g-2 mt-1 epesi-ar-checks">
					<div class="col-sm-6 epesi-ar-check">
						{$form_data.new.html} <span>{$form_data.new.label}</span>
					</div>
					<div class="col-sm-6 epesi-ar-check">
						{$form_data.edit.html} <span>{$form_data.edit.label}</span>
					</div>
					<div class="col-sm-6 epesi-ar-check">
						{$form_data.delete_restore.html} <span>{$form_data.delete_restore.label}</span>
					</div>
					<div class="col-sm-6 epesi-ar-check">
						{$form_data.file.html} <span>{$form_data.file.label}</span>
					</div>
				</div>
				{if isset($form_data.user)}
					<div class="row g-3 mt-1">
						<div class="col-12">
							<label class="form-label">{$form_data.user.label}</label>
							{$form_data.user.html}
						</div>
					</div>
				{/if}
			</div>
		</div>
		{if isset($form_data.submit)}
			<div class="text-center mt-3">
				{$form_data.submit.html}
			</div>
		{/if}
	</div>
</div>
{$form_close}
