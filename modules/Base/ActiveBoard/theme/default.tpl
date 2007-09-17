<table class="container"><tr>
	<td style="width: 80%" class="{$handle_class}">{$caption}</td>
	<td class="controls">{$__link.toggle.open}={$__link.toggle.close} 
	{if isset($configure)}{$__link.configure.open}c{$__link.configure.close} {/if}
	{$__link.remove.open}x{$__link.remove.close}</td>
</tr><tr><td colspan=2 class="content_td">
{$content}
</td></tr></table>
