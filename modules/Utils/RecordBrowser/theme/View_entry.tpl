{assign var=x value=0}
<table id="Utils_RecordBrowser__View_entry" cellpadding="0" cellspacing="1" border="0">
	<tr>
		{foreach key=k item=f from=$fields}
			{if $x==2}
				</tr><tr>
				{assign var=x value=0}
			{/if}
			<td class="label" nowrap>{$f.label}</td>
			<td class="data">{$f.html}</td>
			{assign var=x value=$x+1}
		{/foreach}
		{assign var=z value=$x*-2+4}
		{section name=y loop=$z}
			<td class="label">&nbsp;</td>
		{/section}
	</tr>
</table>
