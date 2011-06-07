<center>

<table id="Base_ActionBar" cellspacing="0" cellpadding="0" style="margin: 10px;">
	<tr>
	{assign var=x value=0}
    {foreach item=i from=$icons}
	{assign var=x value=$x+1}
		<td>
	    {$i.open}
		<div class="launchpad_shadow_css3">
		<div class="big-button">
            {if $display_icon}
            <img src="{$i.icon}" alt="" align="middle" border="0" width="32" height="32">
            {/if}
            {if $display_text}
				<table class="icon_table_text"><tr class="icon_tr_text"><td class="icon_td_text">{$i.label}</td></tr></table>
            {/if}
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
