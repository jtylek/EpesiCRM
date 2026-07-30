{if $fast_install_msg}
<p class="text-muted">{$fast_install_msg}</p>
{/if}
{if $db_error}
<div class="alert alert-danger">{$db_error}</div>
{/if}
{$form_data.javascript}
<form {$form_data.attributes}>
{$form_data.hidden}
<h6 class="mb-3">{$form_data.header.db_header}</h6>

<div class="row mb-2 align-items-center">
	<label class="col-sm-4 col-form-label">{$form_data.host.label} <span class="text-danger">*</span></label>
	<div class="col-sm-8">
		{$form_data.host.html}
		{if $form_data.errors.host}<div class="text-danger small">{$form_data.errors.host}</div>{/if}
	</div>
</div>
<div class="row mb-2 align-items-center">
	<label class="col-sm-4 col-form-label">{$form_data.port.label}</label>
	<div class="col-sm-8">
		{$form_data.port.html}
	</div>
</div>
<div class="row mb-2 align-items-center">
	<label class="col-sm-4 col-form-label">{$form_data.engine.label} <span class="text-danger">*</span></label>
	<div class="col-sm-8">
		{$form_data.engine.html}
		{if $form_data.errors.engine}<div class="text-danger small">{$form_data.errors.engine}</div>{/if}
	</div>
</div>
<div class="row mb-2 align-items-center">
	<label class="col-sm-4 col-form-label">{$form_data.user.label} <span class="text-danger">*</span></label>
	<div class="col-sm-8">
		{$form_data.user.html}
		{if $form_data.errors.user}<div class="text-danger small">{$form_data.errors.user}</div>{/if}
	</div>
</div>
<div class="row mb-2 align-items-center">
	<label class="col-sm-4 col-form-label">{$form_data.password.label} <span class="text-danger">*</span></label>
	<div class="col-sm-8">
		{$form_data.password.html}
		{if $form_data.errors.password}<div class="text-danger small">{$form_data.errors.password}</div>{/if}
	</div>
</div>
<div class="row mb-2 align-items-center">
	<label class="col-sm-4 col-form-label">{$form_data.db.label} <span class="text-danger">*</span></label>
	<div class="col-sm-8">
		{$form_data.db.html}
		{if $form_data.errors.db}<div class="text-danger small">{$form_data.errors.db}</div>{/if}
	</div>
</div>
<div class="row mb-3 align-items-center">
	<label class="col-sm-4 col-form-label">{$form_data.newdb.label}</label>
	<div class="col-sm-8">
		{$form_data.newdb.html}
	</div>
</div>

<h6 class="mb-3">{$form_data.header.other_header}</h6>
<div class="row mb-3 align-items-center">
	<label class="col-sm-4 col-form-label">{$form_data.direction.label}</label>
	<div class="col-sm-8">
		{$form_data.direction.html}
	</div>
</div>

<div class="alert alert-warning">
	<strong>{'Any existing tables will be dropped!'|t}</strong><br />
	{'The database will be populated with data.'|t}<br />
	{'This operation can take several minutes.'|t}
</div>

<div class="text-center">{$form_data.submit.html}</div>
</form>
