
<div id="{$statusbar_id}" class="Base_StatusBar">

<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 10px; width: 300px;">
		<div class="content_shadow" style="border: 4px solid #FFFFFF;">
<!-- -->

<!-- TEMP
			<td class="image"><img src="{$theme_dir}/Base_StatusBar__loader.gif" width="32" height="32" border="0"></td>
			<td class="text"><span id="{$text_id}">Loading...</span></td>
-->

	<table>
		<tr>
			<td class="text"><span id="{$text_id}">Loading...</span></td>
        </tr>
        <tr>
			<td class="image"><img src="{$theme_dir}/Base_StatusBar__loader.gif" width="256" height="10" border="0"></td>
		</tr>
	</table>

<!-- SHADOW END -->
 		</div>
		<div class="shadow-top">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-middle">
			<div class="left"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-bottom">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
	</div>
<!-- -->

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
