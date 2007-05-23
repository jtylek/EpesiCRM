<font size=+2>{$forum_boards}&nbsp;>&nbsp;{$board_name}</font>
<hr>
{foreach item=thread from=$threads}
	<b>{$thread.topic}</b><br>
	{$latest_post}:&nbsp;{$thread.posted_by},&nbsp;{$thread.posted_on}<br>
	{$thread.post_count}
	<hr>
{/foreach}
{if $new_thread}
	{$new_thread}
{/if}