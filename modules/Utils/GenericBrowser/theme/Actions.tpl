{foreach item=action from=$actions}
{assign var=last value=$action.label}
{/foreach}
{foreach key=k item=action from=$actions}

{$action.open}

{if $k=="view"}
<img src="data/Base/Theme/templates/epesi/images/small-icon-view.png" border="0" width="14" height="14">
{else}
{if $k=="delete"}
<img src="data/Base/Theme/templates/epesi/images/small-icon-delete.png" border="0" width="14" height="14">
{else}
{if $k=="edit"}
<img src="data/Base/Theme/templates/epesi/images/small-icon-edit.png" border="0" width="14" height="14">
{else}
{if $k=="info"}
<img src="data/Base/Theme/templates/epesi/images/small-icon-info.png" border="0" width="14" height="14">
{else}
{$action.label}
{/if}		
{/if}		
{/if}		
{/if}		

{$action.close}

{if $last!=$action.label}{/if}
{/foreach}