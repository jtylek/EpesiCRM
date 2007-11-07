{foreach item=action from=$actions}
{assign var=last value=$action.label}
{/foreach}
{foreach key=k item=action from=$actions}

{$action.open}

{if $k=="view" || $k=="delete" || $k=="edit" || $k=="info" || $k=="restore" || $k=="append data"}
<img src="{$theme_dir}/Utils_GenericBrowser__{$k}.gif" border="0" width="14" height="14">
{else}
{$action.label}
{/if}		

{$action.close}

{/foreach}