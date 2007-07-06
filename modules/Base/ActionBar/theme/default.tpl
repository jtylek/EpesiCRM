<ul id="Base_ActionBar">
<li/>
{foreach item=i from=$icons}
<li
{if $display_icon}
 class="{$i.icon}"
{/if}
>
	{if !$display_text}
		<span title="{$i.label}">
	{/if}
	{$i.action_open}
		{if $display_text}
			{$i.label}
		{/if}
	{$i.action_close}
	{if !$display_text}
		</span>
	{/if}
</li>
{/foreach}
</ul>
