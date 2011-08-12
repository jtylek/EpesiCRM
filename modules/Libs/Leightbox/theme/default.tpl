{php}
	load_js($this->get_template_vars('theme_dir').'/Libs/Leightbox/default.js');
{/php}

<div id="Leightbox_header">
    <table border="0" cellpadding="0" cellspacing="0">
        <tbody>
            <tr>
				
                <td class="left" >
					<a class="launchpad_icon_resize" onClick="libs_leightbox_resize(this.parentNode.parentNode.parentNode.parentNode.parentNode.parentNode)">
					<nobr><span class="launchpac_icon_resize_text">{$resize_label}</span></nobr>
					</a>
				</td>
				
				<td class="center">{$header}</td>
				<td class="right">
					<a class="launchpad_icon_close" {$close_href}>
						<nobr><span class="launchpac_icon_close_text">{$close_label}</span></nobr>
					</a>
				</td>
				
			</tr>
        </tbody>
    </table>
</div>

<div id="Leightbox_content">
    {$content}
</div>