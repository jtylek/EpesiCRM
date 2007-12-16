<div id="Utils_TabbedBrowser_div">

<table cellspacing="0" cellpadding="0" border="0" style="width: 100%;">
	<tr>
		<td>
			<ul id="Utils_TabbedBrowser">
			{foreach from=$captions key=cap item=link}
				<li>{$link}</li>&nbsp;
			{/foreach}
			</ul>
		</td>
	</tr>
	<tr>
		<td>
			<center>{$body}</center>
		</td>
	</tr>
</table>

</div>
