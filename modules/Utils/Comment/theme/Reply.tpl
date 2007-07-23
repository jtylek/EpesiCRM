{$form_open}

<table id="Utils_Comment__Reply" cellspacing="0" cellpadding="0" boredr="0">
	<tr>
		<td class="post_label" style="vertical-align: top;">{$form_data.comment_page_reply.label}{$required}</td>
		<td class="post_input">{$form_data.comment_page_reply.error}{$form_data.comment_page_reply.html}</td>
	</tr>
	<tr>
		<td></td>
		<td class="submit" style="text-align: left; padding-top: 5px;">{$form_data.submit_comment.html}&nbsp;{$form_data.cancel_comment.html}</td>
	</tr>	
</table>
{$required}&nbsp;{$required_description}

{$form_close}
