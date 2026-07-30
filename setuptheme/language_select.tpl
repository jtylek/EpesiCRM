<p class="text-muted">{'Select your language'|t}</p>
<div class="d-flex flex-wrap gap-3 justify-content-center">
{foreach from=$complete_langs item=lang}
	<a href="{$lang.href}" class="text-decoration-none text-center" style="width:110px;">
		<img src="{$lang.flag}" class="d-block mx-auto mb-1" style="max-width:64px;" alt="{$lang.label}" />
		<span class="text-body">{$lang.label}</span>
	</a>
{/foreach}
</div>
{if $incomplete_langs}
<div class="text-center mt-3">
	<a class="btn btn-link btn-sm" data-bs-toggle="collapse" href="#incomplete_translations" role="button">
		{'Show incomplete translations'|t}
	</a>
</div>
<div class="collapse" id="incomplete_translations">
	<div class="d-flex flex-wrap gap-3 justify-content-center mt-2">
{foreach from=$incomplete_langs item=lang}
		<a href="{$lang.href}" class="text-decoration-none text-center" style="width:110px;">
			<img src="{$lang.flag}" class="d-block mx-auto mb-1" style="max-width:64px;" alt="{$lang.label}" />
			<span class="text-body">{$lang.label}</span>
		</a>
{/foreach}
	</div>
</div>
{/if}
