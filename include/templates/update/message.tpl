{if $heading}
<p><strong>{$heading}</strong></p>
{/if}
<p>{$message}</p>
{if $link_href}
<div class="mt-3"><a class="btn btn-primary" href="{$link_href}">{$link_text}</a></div>
{/if}
