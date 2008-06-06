<span style="font-size:4 pt;">TEST{*{$row.label}*}</span>
<span style="font-size:8 pt;">TEST{*{$row.label}*}</span>
<span style="font-size:12 pt;">TEST{*{$row.label}*}</span>
<span style="font-size:16 pt;">TEST{*{$row.label}*}</span>

<table border=1 repreat_header=1>
	<tr>
		{foreach item=row from=$cols}
			<th><span style="font-size:2 pt;color:red;">TEST{*{$row.label}*}</span></th>
		{/foreach}
	</tr>
{*	<tr>
		{assign var=x value=0}
		{foreach item=row from=$data}
			{if $x==count($cols)}
				</tr>
				<tr>
				{assign var=x value=0}
			{/if}
			{assign var=x value=$x+1}
			<td><span style="font-size:9px;">!{$row.label}</span></td>
		{/foreach}
	</tr>*}
</table>
