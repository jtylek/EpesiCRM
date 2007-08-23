<table width="80%" class="utils_bookmarkbrowser_browser">
	<tr>
		{foreach from=$list key=cap item=bm}
			<td width={$header_width}% class=utils_bookmarkbrowser_tab>
				<div onmouseover="utils_bookmarkbrowser_markTab(this)" onmouseout="utils_bookmarkbrowser_unmarkTab(this)" class=utils_bookmarkbrowser_tab onclick="utils_bookmark_goto('{$content_id}', 'bkmk_{$bm}')">
					<b>{$bm}</b>
				</div>
			</td>
		{/foreach}
	</tr>
	<tr>
		<td style='border: 1px solid black' colspan="{$groups}">
			<div id={$content_id} class=utils_bookmarkbrowser_pane>
			<table width=100%>
			{foreach from=$items key=title item=items_list}
				<tr><td class=utils_bookmarkbrowser_header colspan=3 id=bkmk_{$title}>{$title}</td></tr>
				{assign var="counter" value=0}
				{foreach from=$items_list key=k item=i}
					{if $counter % 3 == 0}
						<tr>
					{/if}
						<td width=33% style='vertical-align: top;'>{$i}</td>
					{if $counter % 3 == 2}
						</tr>
					{/if}
					{assign var="counter" value=$counter+1}
				{/foreach}
			{/foreach}
			</table>
			</div>
		</td>
	</tr>
</table>
