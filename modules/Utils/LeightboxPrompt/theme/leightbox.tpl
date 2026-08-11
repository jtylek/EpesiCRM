<center>
{$open_buttons_section}
{* Was a <table> hand-wrapping every 6 icons into a new <tr> - flex-wrap
   replaces that, same recipe used app-wide for this pattern (see
   AI-shared/adminlte-theme.md). *}
<div id="Utils_LeightboxPrompt" style="display: flex; flex-wrap: wrap;">
	{foreach item=b from=$buttons}
			{$b.open}
			<div class="epesi_big_button">
				{if ($b.icon)}
					<img src="{$b.icon}" alt="" align="middle" border="0" width="32" height="32">
				{/if}
				<span>{$b.label}</span>
			</div>
			{$b.close}
	{/foreach}
</div>
{$close_buttons_section}

{foreach item=b from=$sections}
	{$b}
{/foreach}
{$additional_info}
</center>
