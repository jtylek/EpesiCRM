<table border="1">
	<tr>
		{assign var=x value=0}
		{foreach item=row from=$cols}			
			<td width="{$table_prefix.widths.$x}" align="center" bgcolor="#888888"><font color="#FFFFFF">{$row.label}</font></td>
			{assign var=x value=$x+1}
		{/foreach}
	</tr>
	<tr>
		{assign var=x value=0}
		{foreach item=row from=$data}
			{if $x==count($cols)}
				</tr>
				<tr>
				{assign var=x value=0}
			{/if}
			<td width="{$table_prefix.widths.$x}" 
				{if strpos($row.attrs,' total-all')}
					bgcolor="#BBBBBB" 
				{else}
					{if strpos($row.attrs,' total')}
						bgcolor="#DDDDDD" 
					{/if} 
				{/if} 
				{if strpos($row.attrs,' number')}
					align="right" 
				{/if}
				height="{$table_prefix.height}">
				<font 
					{if strpos($row.attrs,' fade-out-zero')}
						color="#B0B0B0" 
					{/if}
					>
					{$row.label}
				</font>
			</td>
			{assign var=x value=$x+1}
		{/foreach}
	</tr>
</table>
