{assign var="counter" value=0}

<!-- <div id="tabbed_browser"> -->

<table cellspacing="0" cellpadding="0" border="0" style="width: 100%;">
	<tr>
		<td>
			<ul id="Utils_TabbedBrowser">
			{foreach from=$captions key=cap item=link}
			{if $counter==$selected}
				<li class="selected"><a>{$cap}</a></li>&nbsp;
			{else}
				<li>{$link}</li>&nbsp;
			{/if}
			{assign var="counter" value=$counter+1}
			{/foreach}
			</ul>
		</td>	
	</tr>
	<tr>
		<td style="height: 5px;"></td>
	</tr>
	<tr>
		<td class="body">		<!-- colspan="{$counter}" -->
			<center>{$body}</center>
		</td>
	</tr>
</table>

<!-- </div> -->
