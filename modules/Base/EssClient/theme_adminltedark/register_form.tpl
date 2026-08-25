{* Base_EssClient::register_form() - Company/Administrator details sent to Epesi
   Store Server. $form_data holds every text/commondata field plus the submit
   button (assigned via $f->assign_theme('form', $theme)); intro/notice strings
   are plain already-translated text assigned separately. Two-column layout via
   CSS multi-column balancing (same technique as Utils_RecordBrowser's
   .epesi-rv-fluid, see AI-shared/adminlte-theme.md) - fields keep their PHP
   add-order (Company info, then Address, then Administrator) and the browser
   splits them into two roughly-even columns; labels are right-justified. *}
<div class="card mb-3">
<div class="card-body">

<p class="text-muted small mb-2">{$intro}</p>
{if $auto_filled_notice}<p class="text-muted small mb-1">{$auto_filled_notice}</p>{/if}
{if $revalidation_notice}<p class="text-muted small mb-1">{$revalidation_notice}</p>{/if}
{if $email_change_notice}<p class="text-danger small mb-2">{$email_change_notice}</p>{/if}

{$form_open}
<div class="epesi-reg-grid">
	{foreach from=$form_data item=f}
		{if is_array($f) && isset($f.type) && isset($f.html) && isset($f.label) && $f.type!='hidden' && $f.type!='button' && $f.type!='submit'}
		<div class="epesi-reg-row{if !$f.label} epesi-reg-row--full{/if}">
			{if $f.label}
			<div class="epesi-reg-label">
				{$f.label}{if $f.required}*{/if}
			</div>
			{/if}
			<div class="epesi-reg-data{if $f.frozen} static_field{/if}">
				{$f.error}
				{$f.html}
			</div>
		</div>
		{/if}
	{/foreach}
</div>
<div class="epesi-reg-buttons">
	{foreach from=$form_data item=f}
		{if is_array($f) && isset($f.type) && ($f.type=='button' || $f.type=='submit')}
			{$f.html}
		{/if}
	{/foreach}
</div>
{$form_close}

</div>
</div>
