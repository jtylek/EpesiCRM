<center>
{$open_buttons_section}
<table id="Utils_LeightboxPrompt" cellspacing="0" cellpadding="0">
	{assign var=x value=0}
	<tr>
	{foreach item=b from=$buttons}
        <td>
		<!-- SHADIW BEGIN -->
			<div class="layer" style="padding: 8px; width: 80px;">
				<div class="content_shadow">
		<!-- -->

			    {$b.open}
				<div class="big-button">
					{if ($b.icon)}
						<img src="{$b.icon}" alt="" align="middle" border="0" width="32" height="32">
					{/if}
			        <div style="height: 5px;"></div>
			        <span>{$b.label}</span>
		        </div>
			    {$b.close}

		<!-- SHADOW END -->
		 		</div>
				<div class="shadow-top">
					<div class="left"></div>
					<div class="center"></div>
					<div class="right"></div>
				</div>
				<div class="shadow-middle">
					<div class="left"></div>
					<div class="right"></div>
				</div>
				<div class="shadow-bottom">
					<div class="left"></div>
					<div class="center"></div>
					<div class="right"></div>
				</div>
			</div>
		<!-- -->
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
