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
</table>
{if isset($fav_tooltip)}{$fav_tooltip}{/if}{if isset($info_tooltip)}{$info_tooltip}{/if}           *{$required_note}