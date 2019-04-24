<center>
{$open_buttons_section}
<table id="Utils_LeightboxPrompt" cellspacing="0" cellpadding="0">
	{assign var=x value=0}
	<tr>
	{foreach item=b from=$buttons}
        <td>
			{$b.open}
			<div class="epesi_big_button">
				{if ($b.icon)}
					<img src="{$b.icon}" alt="" align="middle" border="0" width="32" height="32">
				{/if}
				<span>{$b.label}</span>
			</div>
			{$b.close}
        </td>
		{assign var=x value=$x+1}
		{if ($x==6)}
			{assign var=x value=0}
			</tr>
			<tr>
		{/if}
	{/foreach}
    </tr>
</table>
{$close_buttons_section}

{foreach item=b from=$sections}
	{$b}
{/foreach}
{$additional_info}
</center>
