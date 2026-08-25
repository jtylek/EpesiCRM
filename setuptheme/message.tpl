{if $heading}<p><strong>{$heading}</strong></p>{/if}
<p>{$message}</p>
{if $pre}
{if $pre_collapsed}
<details class="mb-2">
	<summary class="text-muted" style="cursor:pointer">{if $pre_label}{$pre_label}{else}{'Show technical details'|t}{/if}</summary>
	<pre class="bg-light p-2 border rounded mt-2">{$pre|escape}</pre>
</details>
{else}
<pre class="bg-light p-2 border rounded">{$pre|escape}</pre>
{/if}
{/if}
{if $link_href}
<div class="text-center mt-3"><a class="btn btn-primary" href="{$link_href}">{$link_text}</a></div>
{/if}
