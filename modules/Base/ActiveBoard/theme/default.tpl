<div class="applet">
<div class="{$handle_class}"><table style="width: 100%"><tr>
	<td style="width: 80%">{$caption}</td>
	<td style="text-align:right">{$__link.toggle.open}={$__link.toggle.close} 
	{if isset($configure)}{$__link.configure.open}c{$__link.configure.close} {/if}
	{$__link.remove.open}x{$__link.remove.close}</td>
</tr></table></div>
{$content}
</div>