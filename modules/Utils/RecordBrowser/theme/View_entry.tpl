{assign var=x value=0}
<table border=1>
	<tr>
		{foreach key=k item=f from=$fields}
			{if $x==2}
				</tr><tr>
				{assign var=x value=0}
			{/if} 
			<td>{$f.label}</td>
			<td>{$f.html}</td>
			{assign var=x value=$x+1}
		{/foreach}
		{assign var=z value=$x*-2+4}
		{section name=y loop=$z}
			<td></td>
		{/section}
	</tr>
</table>