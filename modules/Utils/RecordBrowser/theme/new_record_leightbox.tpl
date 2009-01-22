<center>
<br>
<table id="CRM_Filters" cellspacing="0" cellpadding="0">
	<tr>
{foreach key=k item=cd from=$custom_defaults}
	        <td>

	<!-- SHADIW BEGIN -->
		<div class="layer" style="padding: 8px; width: 110px;">
			<div class="content_shadow">
	<!-- -->

		    {$cd.open}
			<div class="big-button">
	            <img src="{$cd.icon}" alt="" align="middle" border="0" width="32" height="32">
	            <div style="height: 5px;"></div>
	            <span>{$cd.label}</span>
	        </div>
		    {$cd.close}


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

	        </td>
{/foreach}
    </tr>
</table>

</center>
