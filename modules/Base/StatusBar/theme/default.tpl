<div id="{$statusbar_id}" class="Base_StatusBar" style="position: absolute;">
	<table>
		<tr>
			<td class="image"><img src="{$theme_dir}/Base_StatusBar__loader.gif" width="16" height="16" border="0"></td>
			<td class="text">
				<span id="{$text_id}">Loading...</span>
			</td>
		</tr>
	</table>
</div>
{literal}
<!--[if gte IE 5.5]><![if lt IE 7]>
<style type="text/css">
.Base_StatusBar {
	position: absolute;
  	top: expression( ignoreMe = (document.documentElement.scrollTop + document.body.clientHeight/4) + 'px' );
}
</style>
<![endif]><![endif]-->
{/literal}
