{php}
	load_js('data/Base_Theme/templates/default/Libs_Leightbox__default.js');
{/php}

<div id="Launchpad_header">
    <div class="left"><img src="{$theme_dir}/Libs_Leightbox__resize.png" onClick="libs_leightbox_resize(this.parentNode.parentNode.parentNode)" onMouseOver="this.src='{$theme_dir}/Libs_Leightbox__resize-hover.png';" onMouseOut="this.src='{$theme_dir}/Libs_Leightbox__resize.png';" width="14" height="14" alt="R" border="0"></div>
    <div class="center">{$header}</div>
	<div class="right"><a {$close_href}><img src="{$theme_dir}/Libs_Leightbox__close.png" onMouseOver="this.src='{$theme_dir}/Libs_Leightbox__close-hover.png';" onMouseOut="this.src='{$theme_dir}/Libs_Leightbox__close.png';" width="14" height="14" alt="X" border="0"></a></div>
</div>

{$content}
