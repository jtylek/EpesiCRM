{foreach item=i from=$icons}
<span>
{if $display_icon}
{$i.icon}
{/if}
{if $display_text}
{$i.action_open}{$i.label}{$i.action_close}
{/if}
</span>
{/foreach}