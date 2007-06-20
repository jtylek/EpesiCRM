<br>
<br>
<table id="Apps_Forum__Boards" cellspacing="0" cellpadding="0" boredr="0">
	<tr>
		<td class="header">
			{$forum_boards}
		</td>
	</tr>
	<tr>
		<td style="border-bottom: 4px solid #CCCCCC;">
			<center>
			<table cellspacing="0" cellpadding="0" boredr="0" style="width: 950px;">
				{foreach item=board from=$boards}
					<tr>
						<td style="height: 30px; border-bottom: 1px solid #CCCCCC; border-right: 1px solid #CCCCCC; width: 30%; text-align: left; padding-left: 10px; font-weight: bold;">
							{$board.label}
						</td>
						<td style="height: 30px; border-bottom: 1px solid #CCCCCC; border-right: 1px solid #CCCCCC; width: 60%; text-align: left; padding-left: 10px;">
							{$board.descr}
						</td>
						<td style="height: 30px; border-bottom: 1px solid #CCCCCC; width: 10%; text-align: left; padding-left: 10px;">
							{if $board.delete}{$board.delete}{/if}
						</td>
					</tr>	
				{/foreach}
			</table>
			</center>		
		</td>
	</tr>
</table>			