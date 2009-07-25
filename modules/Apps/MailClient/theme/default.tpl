<table class="Apps_MailClient_header" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td class="arrow" style="padding-left: 20px;">&nbsp;</td>
			<td class="icon"><img src="{$theme_dir}/Apps/MailClient/icon.png" width="32" height="32" border="0"></td>
			<td class="arrow">&nbsp;</td>
			<td class="name">Mail Client</td>
			<td class="required_fav_info">&nbsp;</td>
		</tr>
	</tbody>
</table>

<!-- SHADOW BEGIN -->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="content_shadow">
<!-- -->

<div style="padding: 2px 2px 2px 2px; background-color: #FFFFFF;">

<table id="Apps_MailClient" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td rowspan="3" class="menu">{$tree}</td>
			<td class="list">{$list}</td>
		</tr>
		<tr>
			<td class="space">=</td>
		</tr>
		<tr>
			<td class="content">
				<table class="mail" border="0" cellpadding="0" cellspacing="0">
					<tbody>
						<tr>
							<td class="label">{$subject_label}</td>
							<td class="data">{$preview_subject}</td>
							<td style="width: 100px; text-align: right; border-bottom: 1px solid #b3b3b3; border-left: 1px solid #b3b3b3; vertical-align: middle; text-align: center;" rowspan="2">{$preview_attachments}</td>
						</tr>
						<tr>
							<td class="label">{$address_label}</td>
							<td class="data">{$preview_address}</td>
						</tr>
						<tr>
							<td colspan="3" class="preview_body">{$preview_body}</td>
						</tr>
					</tbody>
				</table>
			</td>
		</tr>
	</tbody>
</table>

</div>

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
