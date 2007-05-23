<table class="Base_User_Settings">
	<tr>
		<td class="header">{$header}</td>
	</tr>
	{foreach key=key item=link from=$links}
		<tr>
			<td>{$link}</td>
			<!-- $key holds name of the module -->
		</tr>
	{/foreach}
</table>
