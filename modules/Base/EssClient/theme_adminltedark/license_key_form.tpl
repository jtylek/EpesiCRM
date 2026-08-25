{* Base_EssClient::license_key_form() - manual License Key entry/recovery
   screen (system recovery/migration only). $form_data holds the single
   'license_key' text field (assigned via $f->assign_theme('form', $theme));
   $notice is plain already-translated warning text. Save lives in the top
   action bar (Base_ActionBarCommon::add('save', ...), PHP-side), not a
   form submit button here - same as Base_EssClient::no_ssl_settings(). *}
<div class="bg-body-tertiary rounded p-3 mb-3 text-center">{$notice}</div>
<div class="card mb-3">
	<div class="card-body">
		{$form_open}
		<div class="epesi-lk-row">
			<label for="license_key" class="epesi-lk-label">{$form_data.license_key.label}</label>
			<div class="epesi-lk-data">
				{$form_data.license_key.error}
				{$form_data.license_key.html}
			</div>
		</div>
		{$form_close}
	</div>
</div>
