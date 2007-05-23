<font size=+2>{$forum_boards}</font>
<hr>
{foreach item=board from=$boards}
	<div align=left>
		<font size=+2><b>{$board.label}</b></font><br>
		{$board.descr}
	</div>
	<hr>
{/foreach}
{if $add_board}
	{$add_board}
{/if}