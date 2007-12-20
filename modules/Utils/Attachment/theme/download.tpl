{php}
	load_js('data/Base_Theme/templates/default/Utils_Attachment__download.js');
{/php}

<center>

<div id="Attachment_header">
    <div class="left"><img src="{$theme_dir}/Utils_Attachment__resize.png" onClick="resize_download();" onMouseOver="this.src='{$theme_dir}/Utils_Attachment__resize-hover.png';" onMouseOut="this.src='{$theme_dir}/Utils_Attachment__resize.png';" width="14" height="14" alt="R" border="0"></div>
    <div class="center"><div style="position: absolute; left: 45%;">Attachment</div></div>
	<div class="right">{$__link.close.open}<img src="{$theme_dir}/Utils_Attachment__close.png" onMouseOver="this.src='{$theme_dir}/Utils_Attachment__close-hover.png';" onMouseOut="this.src='{$theme_dir}/Utils_Attachment__close.png';" width="14" height="14" alt="X" border="0">{$__link.close.close}</div>
</div>
<br/>
<h3>{$filename}</h3>
<br/>

<table id="Utils_Attachment__download" cellspacing="0" cellpadding="0">
	<tr>
        <!-- VIEW -->
        <td>
            <!-- SHADIW BEGIN -->
            <div class="layer" style="padding: 8px; width: 120px;">
            	<div class="content_shadow">
            <!-- -->
                    {$__link.view.open}
                    <div class="button">
                        {*{if $display_icon}*}
                        <img src="{$theme_dir}/Utils_Attachment__view.png" alt="" align="middle" border="0" width="32" height="32">
                        {*{/if}*}
                        {*{if $display_text}*}
                            <div style="height: 5px;"></div>
                            <span>{$__link.view.text}</span>
                        {*{/if}*}
                    </div>
                    {$__link.view.close}
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
        <!-- DOWNLOAD -->
        <td>
            <!-- SHADIW BEGIN -->
            <div class="layer" style="padding: 8px; width: 120px;">
            	<div class="content_shadow">
            <!-- -->
                    {$__link.download.open}
                    <div class="button">
                        {*{if $display_icon}*}
                        <img src="{$theme_dir}/Utils_Attachment__download.png" alt="" align="middle" border="0" width="32" height="32">
                        {*{/if}*}
                        {*{if $display_text}*}
                            <div style="height: 5px;"></div>
                            <span>{$__link.download.text}</span>
                        {*{/if}*}
                    </div>
                    {$__link.download.close}
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
        <!-- LINK -->
        <td>
            <!-- SHADIW BEGIN -->
            <div class="layer" style="padding: 8px; width: 120px;">
            	<div class="content_shadow">
            <!-- -->
                    {$__link.link.open}
                    <div class="button">
                        {*{if $display_icon}*}
                        <img src="{$theme_dir}/Utils_Attachment__link.png" alt="" align="middle" border="0" width="32" height="32">
                        {*{/if}*}
                        {*{if $display_text}*}
                            <div style="height: 5px;"></div>
                            <span>{$__link.link.text}</span>
                        {*{/if}*}
                    </div>
                    {$__link.link.close}
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
    </tr>
</table>
