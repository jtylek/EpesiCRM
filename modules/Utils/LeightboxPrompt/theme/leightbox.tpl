<center>
{$open_buttons_section}
<table id="Utils_LeightboxPrompt" cellspacing="0" cellpadding="0">
	{assign var=x value=0}
	<tr>
	{foreach item=b from=$buttons}
        <td>
			<div class="leightbox_shadow_css3">

			    {$b.open}
				<div class="big-button">
					{if ($b.icon)}
						<img src="{$b.icon}" alt="" align="middle" border="0" width="32" height="32">
					{/if}
			        <div style="height: 5px;"></div>
			        <table class="icon_table_text"><tr class="icon_tr_text"><td class="icon_td_text">{$b.label}</td></tr></table>
		        </div>
			    {$b.close}
		 	</div>
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
