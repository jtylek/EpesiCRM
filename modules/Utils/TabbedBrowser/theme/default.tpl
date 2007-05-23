{assign var="counter" value=0}
<div id="tabbed_browser">
<table width="100%" class="browser"><tr>
{foreach from=$captions key=cap item=link}
    {if $counter==$selected}
	<td class="selected">
		{$cap}
	</td>
    {else}
	<td class="unselected">
		{$link}
	</td>
    {/if}
    {assign var="counter" value=$counter+1}
{/foreach}
</tr><tr><td colspan="{$counter}" class="body">
<center>{$body}</center>
</td></tr></table>
</div>
