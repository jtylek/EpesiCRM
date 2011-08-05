
<div id="{$statusbar_id}" class="Base_StatusBar">
	<div class="layer" style="width: 300px; height: 60px;">
		<div class="shadow_5px border_radius_6px">
<!-- TEMP
			<td class="image"><img src="{$theme_dir}/Base/StatusBar/loader.gif" width="32" height="32" border="0"></td>
			<td class="text"><span id="{$text_id}">Loading...</span></td>
-->
			<table border="0" cellpadding="0" cellspacing="0">
				<tbody>
					<tr>
						<td class="sb_text"><span id="{$text_id}">Loading...</span></td>
					</tr>
					<tr>
						<td class="sb_image"><img src="{$theme_dir}/Base/StatusBar/loader.gif" width="256" height="10" border="0"></td>
					</tr>
				</tbody>
			</table>
 		</div>
	</div>
</div>

{literal}
<!--[if gte IE 5.5]><![if lt IE 7]>
<style type="text/css">
.Base_StatusBar {
	position: absolute;
  	top: expression( ignoreMe = (document.documentElement.scrollTop + document.body.clientHeight/4) + 'px' );
}
.Base_StatusBar div.layer {
    padding-bottom: 8px;
}
</style>
<![endif]><![endif]-->
{/literal}
