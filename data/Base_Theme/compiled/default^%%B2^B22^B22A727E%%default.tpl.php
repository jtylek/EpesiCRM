<?php /* Smarty version 2.6.29, created on 2026-06-20 21:14:47
         compiled from Libs/Leightbox/default.tpl */ ?>
<?php 
	load_js($this->get_template_vars('theme_dir').'/Libs/Leightbox/default.js');
 ?>

<div id="Leightbox_header">
    <table border="0" cellpadding="0" cellspacing="0">
        <tbody>
            <tr>
				
                <td class="left" >
					<a class="launchpad_icon_resize" onClick="libs_leightbox_resize(this.parentNode.parentNode.parentNode.parentNode.parentNode.parentNode)">
					<nobr><span class="launchpad_icon_resize_text"><?php echo $this->_tpl_vars['resize_label']; ?>
</span></nobr>
					</a>
				</td>
				
				<td class="center"><?php echo $this->_tpl_vars['header']; ?>
</td>
				<td class="right">
					<a class="launchpad_icon_close" <?php echo $this->_tpl_vars['close_href']; ?>
>
						<nobr><span class="launchpad_icon_close_text"><?php echo $this->_tpl_vars['close_label']; ?>
</span></nobr>
					</a>
				</td>
				
			</tr>
        </tbody>
    </table>
</div>

<div id="Leightbox_content">
    <?php echo $this->_tpl_vars['content']; ?>

</div>