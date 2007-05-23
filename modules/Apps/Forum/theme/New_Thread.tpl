<font size=+2>{$forum_boards}&nbsp;>&nbsp;{$board_name}</font>
<hr>
{$form_data.javascript}
<form {$form_data.attributes}> 
	{$form_data.hidden}
	{$form_data.header.new_thread}<br>
	{$form_data.topic.label}{$form_data.topic.html}<br>
	{$form_data.post.label}{$form_data.post.html}<br>
	{$form_data.submit.html}{$form_data.cancel.html}
</form>
