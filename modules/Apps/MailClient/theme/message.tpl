<div id="Apps_MailClient_message">
	<table id="Apps_MailClient" class="mail" border="0" cellpadding="0" cellspacing="0">
		<tbody>
			<tr>
				<td colspan="3" style="height: 20px;">
					<div id="Leightbox_header">
						<table border="0" cellpadding="0" cellspacing="0">
							<tbody>
								<tr>
									<td class="left"><img src="{$theme_dir}/Libs/Leightbox/resize.png" onClick="libs_leightbox_resize(this.parentNode.parentNode.parentNode.parentNode.parentNode.parentNode)" onMouseOver="this.src='{$theme_dir}/Libs/Leightbox/resize-hover.png';" onMouseOut="this.src='{$theme_dir}/Libs/Leightbox/resize.png';" width="14" height="14" alt="R" border="0" /></td>
									<td class="center">View Mail - {$close}</td>
									<td class="right"><a><img src="{$theme_dir}/Libs/Leightbox/close.png" onMouseOver="this.src='{$theme_dir}/Libs/Leightbox/close-hover.png';" onMouseOut="this.src='{$theme_dir}/Libs/Leightbox/close.png';" width="14" height="14" alt="X" border="0" /></a></td>
								</tr>
							</tbody>
						</table>
					</div>
				</td>
			</tr>
			<tr>
				<td class="label">{$subject_label}</td>
				<td class="data">{$subject}</td>
				<td rclass="data" owspan="2">{$attachments}</td>
			</tr>
			<tr>
				<td class="label">{$address_label}</td>
				<td class="data">{$address}</td>
			</tr>
			<tr>
				<td class="preview_body" colspan="3">{$body}</td>
			</tr>
		</tbody>
	</table>
</div>
