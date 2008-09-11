{$form_open}

<table id="Apps_Forum__New_Thread" cellspacing="0" cellpadding="0" boredr="0">
	<tr>
		<td class="header" colspan="3">
			{$forum_boards}&nbsp;<img border="0" src="{$theme_dir}/Utils/Path/arrow.gif" width="11" height="11">&nbsp;{$board_name}
		</td>
	</tr>
	<tr>
		<td class="topic_label">{$form_data.topic.label}{$required}</td>
		<td class="topic_input" colspan="2">{$form_data.topic.error}{$form_data.topic.html}</td>
	</tr>
	<tr>
		<td class="post_label" style="vertical-align: top;">{$form_data.post.label}{$required}</td>
		<td class="post_input" colspan="2">{$form_data.post.error}{$form_data.post.html}</td>
	</tr>
	<tr>
		<td></td>
		<td class="submit" colspan="2" style="text-align: left; padding-top: 5px;">{$form_data.submit.html}&nbsp;{$form_data.cancel.html}</td>
	</tr>	
</table>
{$required}&nbsp;{$required_description}

{$form_close}
