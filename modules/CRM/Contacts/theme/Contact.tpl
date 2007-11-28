{if isset($Form_data.paste_company_info)}
{$Form_data.paste_company_info.html}
{/if}
{assign var=x value=0}
<table id="Utils_RecordBrowser__View_entry" cellpadding="0" cellspacing="1" border="0">
	<tr>
		{foreach key=k item=f from=$fields}
			{if $x==2}
				</tr><tr>
				{assign var=x value=0}
			{/if}
			<td class="label" nowrap>{$f.label}{if $f.required}*{/if}</td>
			<td class="data">{if $f.error}{$f.error}{/if}{$f.html}</td>
			{assign var=x value=$x+1}
		{/foreach}
		{assign var=z value=$x*-2+4}
		{section name=y loop=$z}
			<td class="label">&nbsp;</td>
		{/section}
	</tr>
	{if isset($Form_data.create_company)}
	<tr>
		<td class="label" nowrap>
			{$Form_data.create_company.label}
		</td>
		<td class="data">
			{$Form_data.create_company.html}
		</td>
		<td />
	</tr>
	{/if}
</table>
{if isset($fav_tooltip)}{$fav_tooltip}{/if}{if isset($info_tooltip)}{$info_tooltip}{/if}           *{$required_note}
