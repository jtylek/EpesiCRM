{* Narrows the surrounding .card (admin/'s layout.tpl - the only current
   caller with one) rather than just this template's own content, since a
   half-width form floating inside a still-full-width white card looks the
   same as not narrowing anything. :has() is already relied on elsewhere in
   this codebase's adminlte CSS (e.g. GenericBrowser's icon rules). No effect
   in update.php/check.php, which don't wrap this template in a .card at all
   (and don't load Bootstrap either). *}
<style>
{literal}
.card:has(.epesi-simple-login) {
	max-width: 50%;
	margin: 0 auto;
}
@media (max-width: 575.98px) {
	.card:has(.epesi-simple-login) {
		max-width: 100%;
	}
}
{/literal}
</style>
<div class="epesi-simple-login">
{$form_data.javascript}
<p class="login-box-msg">{$login_box_msg}</p>
<form {$form_data.attributes}>
{$form_data.hidden}
<div class="text-danger small mb-1">{$form_data.username.error}</div>
<div class="input-group mb-3">
	{$form_data.username.html}
	<div class="input-group-text"><i class="bi bi-person"></i></div>
</div>
<div class="text-danger small mb-1">{$form_data.password.error}</div>
<div class="input-group mb-3">
	{$form_data.password.html}
	<div class="input-group-text"><i class="bi bi-lock-fill"></i></div>
</div>
<div class="text-muted small mb-3">{$form_data.requirednote}</div>
<div class="d-flex justify-content-center">
	{$form_data.submit_button.html}
</div>
</form>
</div>
