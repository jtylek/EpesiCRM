{if $heading}<p><strong>{$heading}</strong></p>{/if}
<p>{$message}</p>
{if $pre}<pre class="bg-light p-2 border rounded">{$pre}</pre>{/if}
{if $link_href}
<div class="text-center mt-3"><a class="btn btn-primary" href="{$link_href}">{$link_text}</a></div>
{/if}
