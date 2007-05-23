{$form_data.javascript}
<form {$form_data.attributes}> 
	{$form_data.hidden}
	{if $form_data.header.reply}
		{$form_data.header.reply}<br>
	{/if}
	{$form_data.comment_page_reply.label}{$form_data.comment_page_reply.html}<br>
	{$form_data.submit_comment.html}{$form_data.cancel_comment.html}
</form>
