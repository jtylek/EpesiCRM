<center>

<table id="Base_ActionBar__launchpad" cellspacing="0" cellpadding="0" style="margin: 10px;">
	<tr>
	{assign var=x value=0}
    {foreach item=i from=$icons}
	{assign var=x value=$x+1}
		<td>
	    {$i.open}
		<div class="btn btn-default btn-lg">
            <div class="div_icon">{if $i.icon_url}
			          <img src="{$i.icon_url}" style="height:2em">
			      {else}
			          <i class="fa fa-{$i.icon} fa-2x"></i>
			      {/if}</div>
            <span>{$i.label}</span>
        </div>
	    {$i.close}
		</td>
	{if ($x%5)==0}
	</tr>
	<tr>
	{/if}
{/foreach}

	</tr>
</table>

</center>
