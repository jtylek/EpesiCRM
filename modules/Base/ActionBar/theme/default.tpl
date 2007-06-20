<ul id="Base_ActionBar">
<li/>
{foreach item=i from=$icons}
<li
{if $display_icon}
 class="{$i.icon}"
{/if}
>
{if $display_text}
{$i.action_open}{$i.label}{$i.action_close}
{/if}
</li>
{/foreach}
</ul>