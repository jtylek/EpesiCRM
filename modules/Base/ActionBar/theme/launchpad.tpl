<center>

<table id="Base_ActionBar__launchpad" cellspacing="0" cellpadding="0" style="margin: 10px;">
	<tr>
	{assign var=x value=0}
    {foreach item=i from=$icons}
	{assign var=x value=$x+1}
		<td>
	    {$i.open}
		<div class="epesi_big_button">
            {if $display_icon}
            <img src="{$i.icon}" alt="" align="middle" border="0" width="32" height="32">
            {/if}
            {if $display_text}
				<span>{$i.label}</span>
            {/if}
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
