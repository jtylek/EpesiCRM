<table border="1">
	<tr>
		{assign var=x value=0}
		{foreach item=field from=$row}
			<td width="{$params.widths.$x}" 
				{if isset($field.style.total_all)}
					bgcolor="#BBBBBB" 
				{else}
					{if isset($field.style.total)}
						bgcolor="#DDDDDD" 
					{/if} 
				{/if} 
				{if isset($field.style.numeric) || isset($field.style.currency)}
					align="right" 
				{/if}
				{if isset($field.style.header)}
					align="center" bgcolor="#888888" 
				{/if}
				height="{$params.height}">
				<font 
					{if isset($field.style.fade_out_zero)}
						color="#B0B0B0" 
					{/if}
					{if isset($field.style.header)}
						color="#FFFFFF"
					{/if}
					>
					{$field.value}
				</font>
			</td>
			{assign var=x value=$x+1}
		{/foreach}
	</tr>
</table>
