{$form_open}

<div id="Utils_Comment__Reply" role="table" style="display: grid; grid-template-columns: 100px 850px;">
	<div class="post_label" role="cell" style="vertical-align: top;">{$form_data.comment_page_reply.label}{$required}</div>
	<div class="post_input" role="cell">{$form_data.comment_page_reply.error}{$form_data.comment_page_reply.html}</div>
	<div role="presentation"></div>
	<div class="submit" role="cell" style="text-align: left; padding-top: 5px;">{$form_data.submit_comment.html}&nbsp;{$form_data.cancel_comment.html}</div>
</div>

{$form_close}
