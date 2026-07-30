<div class="setup-license-text mb-3">{$license_html}</div>
{$form_data.javascript}
<form {$form_data.attributes}>
{$form_data.hidden}
<div class="form-check mb-2">{$form_data.tos1.html}</div>
{if $form_data.errors.tos1}<div class="text-danger small mb-2">{$form_data.errors.tos1}</div>{/if}
<div class="form-check mb-2">{$form_data.tos2.html}</div>
{if $form_data.errors.tos2}<div class="text-danger small mb-2">{$form_data.errors.tos2}</div>{/if}
<div class="form-check mb-2">{$form_data.tos3.html}</div>
{if $form_data.errors.tos3}<div class="text-danger small mb-2">{$form_data.errors.tos3}</div>{/if}
<div class="form-check mb-3">{$form_data.tos4.html}</div>
{if $form_data.errors.tos4}<div class="text-danger small mb-2">{$form_data.errors.tos4}</div>{/if}
<div class="text-center">{$form_data.submit.html}</div>
</form>
