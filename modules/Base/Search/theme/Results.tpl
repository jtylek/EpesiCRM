{if isset($links)}
<table id="Base_Search__Results" cellspacing="0" cellpadding="0" border="0">
	<tr>
		<td class="header">{$header}</td>
	</tr>
	{foreach key=key item=link from=$links}
	<tr>
		<td class="link">{$link}</td>
		<!-- $key holds name of the module -->
	</tr>
	{/foreach}
</table>
{/if}