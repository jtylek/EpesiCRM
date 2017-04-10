<center>

<table id="Base_ActionBar__launchpad" cellspacing="0" cellpadding="0" style="margin: 10px;">
	<tr>
	{assign var=x value=0}
    {foreach item=i from=$icons}
	{assign var=x value=$x+1}
		<td style="padding: 5px;">
	    {$i.open}
		<div class="btn btn-default btn-lg" style="width: 15rem; height: 9rem">
			<div style="padding: 5px">
				<div class="div_icon">{if $i.icon_url}
						<img src="{$i.icon_url}" style="height:2em">
                    {else}
						<i class="fa fa-{$i.icon} fa-2x"></i>
                    {/if}
				</div>
				<span style="font-size: 13px">{$i.label}</span>
			</div>
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
