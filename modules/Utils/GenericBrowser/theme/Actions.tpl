{foreach item=action from=$actions}
{assign var=last value=$action.label}
{/foreach}
{foreach item=action from=$actions}
{$action.open}{$action.label}{$action.close}
{if $last!=$action.label}::&nbsp;{/if}
{/foreach}