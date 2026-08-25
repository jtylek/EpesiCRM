{* Base_EssClient::terms_and_conditions() - Epesi Store registration gate: accept
   Terms and Conditions to obtain a License Key, or follow the link to enter one
   already issued. $form_data holds the 'agree' checkbox + 'submit' button
   (assigned via $form->assign_theme('form', $theme)); every other variable here
   is plain already-translated text assigned by the same method - see its
   default (legacy) theme rendering for the identical copy/links this mirrors. *}
<div class="card mb-3">
	<div class="card-body">
		<h3 class="text-center mb-3">{$title}</h3>

		<p>{$intro}</p>
		<p>{$license_key_intro}</p>
		<p class="mb-1"><strong>{$enter_license_key_label}</strong> <a {$enter_license_key_href}>{$enter_license_key_text}</a></p>
		<p>{$license_key_move}</p>
		<p>{$tos_label} <a target="_blank" href="{$tos_href}">{$tos_text}</a></p>

		{$form_open}
		<div class="d-flex flex-column align-items-center gap-2">
			<div class="form-check">
				{$form_data.agree.html}
				<label class="form-check-label" for="agree">{$form_data.agree.label}</label>
			</div>
			<div class="text-danger small">{$form_data.agree.error}</div>
			{$form_data.submit.html}
		</div>
		{$form_close}
	</div>
</div>
