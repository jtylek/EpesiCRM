<table id="Apps_Forum__Threads" cellspacing="0" cellpadding="0" boredr="0">
	<tr>
		<td class="header">
			{$forum_boards}&nbsp;<img border="0" src="{$theme_dir}/Utils/Path/arrow.gif" width="11" height="11">&nbsp;{$board_name}
		</td>
	</tr>
	<tr>
		<td style="border-bottom: 3px solid #CCCCCC;">
			<table cellspacing="0" cellpadding="0" boredr="0" style="width: 950px;">
				<tr>
					<td style="background-color: #F8F8F8; height: 20px; border-bottom: 4px solid #CCCCCC; border-right: 1px solid #CCCCCC; width: 20%; text-align: center;">{$topic}</td>
					<td style="background-color: #F8F8F8; height: 20px; border-bottom: 4px solid #CCCCCC; border-right: 1px solid #CCCCCC; width: 20%; text-align: center;" colspan="2">{$latest_post}</td>
					<td style="background-color: #F8F8F8; height: 20px; border-bottom: 4px solid #CCCCCC; border-right: 1px solid #CCCCCC; width: 10%; text-align: center">{$posts_count}</td>
					<td style="background-color: #F8F8F8; height: 20px; border-bottom: 4px solid #CCCCCC; width: 10%; text-align: center">Actions</td>
				</tr>
				{foreach item=thread from=$threads}
				<tr>
					<td style="height: 30px; border-bottom: 1px solid #CCCCCC; border-right: 1px solid #CCCCCC; width: 20%; text-align: left; padding-left: 10px; font-weight: bold;">{$thread.topic}</td>
					<td style="height: 30px; border-bottom: 1px solid #CCCCCC; border-right: 1px solid #CCCCCC; width: 30%; text-align: left; padding-left: 10px;">{$thread.posted_by}</td>
					<td style="height: 30px; border-bottom: 1px solid #CCCCCC; border-right: 1px solid #CCCCCC; width: 30%; text-align: left; padding-left: 10px;">{$thread.posted_on}</td>
					<td style="height: 30px; border-bottom: 1px solid #CCCCCC; border-right: 1px solid #CCCCCC; width: 10%; text-align: left; padding-left: 10px;">{$thread.post_count}</td>
					<td style="height: 30px; border-bottom: 1px solid #CCCCCC; width: 10%; text-align: left; padding-left: 10px;">{if $thread.delete}{$thread.delete}{/if}</td>
				</tr>
				{/foreach}
			</table>
		</td>
	</tr>
</table>