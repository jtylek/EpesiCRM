<table id="Utils_Planner__grid">
	<tr>
		<td/>
		{foreach item=h from=$headers}
			<td class="header">
				{$h}
			</td>
		{/foreach}
	</tr>
	{foreach item=gl key=gk from=$grid_legend}
		<tr>
			<td class="grid_legend">
				{$gl}
			</td>
			{foreach item=h key=k from=$headers}
				<td {$grid_attrs.$gk.$k}>
				</td>
			{/foreach}
			<br>
		</tr>
	{/foreach}
</table>