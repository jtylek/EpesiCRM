<h4>{$heading}</h4>
<p>{$intro}</p>
<ul>
{foreach from=$modules item=m}
	<li>{$m|escape}</li>
{/foreach}
</ul>
<div class="alert alert-warning fw-semibold">{$warning}</div>
<p>{$data_note}</p>
<div class="mt-3"><a class="btn btn-primary" href="{$proceed_url}">{$confirm_label}</a></div>
