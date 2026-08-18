<div class="d-flex flex-wrap gap-3 justify-content-center">
{foreach from=$langs_list item=lang}
	<a href="{$lang.href}" class="text-decoration-none text-center" style="width:110px;">
		<img src="{$lang.flag}" class="d-block mx-auto mb-1" style="max-width:64px;" alt="{$lang.label}" />
		<span class="text-body">{$lang.label}</span>
	</a>
{/foreach}
</div>
