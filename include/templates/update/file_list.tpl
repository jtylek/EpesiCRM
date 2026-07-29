<p><strong>{$heading}:</strong></p>
<ul>
{foreach from=$files item=f}
	<li>{$f|escape}</li>
{/foreach}
</ul>
{if $link_href}
<div class="mt-3"><a class="btn btn-primary" href="{$link_href}">{$link_text}</a></div>
{/if}
