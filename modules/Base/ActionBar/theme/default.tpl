<div style="text-align:left">
<table id="Base_ActionBar">
<tr>
{foreach item=i from=$icons}
<td>
	{if !$display_text}
		<span title="{$i.label}">
	{/if}
	{$i.action_open}
		{if $display_icon}
			<img src="{$theme_dir}/images/icons/icon-{$i.icon}.png" onmouseover='this.src="{$theme_dir}/images/icons/icon-{$i.icon}-hover.png"' onmouseout='this.src="{$theme_dir}/images/icons/icon-{$i.icon}.png"'>
			{if $display_text}
			<br>
			{/if}
		{/if}
	
		{if $display_text}
			{$i.label}
		{/if}
	{$i.action_close}
	{if !$display_text}
		</span>
	{/if}
</td>
{/foreach}
</tr>
</table></div>