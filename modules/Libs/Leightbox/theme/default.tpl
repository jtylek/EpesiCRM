{php}
	load_js($this->get_template_vars('theme_dir').'/Libs/Leightbox/default.js');
{/php}

<div id="Leightbox_header">
    <table border="0" cellpadding="0" cellspacing="0">
        <tbody>
            <tr>
                <td class="left"><img src="{$theme_dir}/Libs/Leightbox/resize.png" onClick="libs_leightbox_resize(this.parentNode.parentNode.parentNode.parentNode.parentNode.parentNode)" onMouseOver="this.src='{$theme_dir}/Libs/Leightbox/resize-hover.png';" onMouseOut="this.src='{$theme_dir}/Libs/Leightbox/resize.png';" width="14" height="14" alt="R" border="0" /></td>
                <td class="center">{$header}</td>
                <td class="right"><a {$close_href}><img src="{$theme_dir}/Libs/Leightbox/close.png" onMouseOver="this.src='{$theme_dir}/Libs/Leightbox/close-hover.png';" onMouseOut="this.src='{$theme_dir}/Libs/Leightbox/close.png';" width="14" height="14" alt="X" border="0" /></a></td>
            </tr>
        </tbody>
    </table>
</div>

<div id="Leightbox_content">
    {$content}
</div>
