<table class="Utils_Attachment__table" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td class="icon"><img src="{$theme_dir}/Utils/Attachment/icon.png" width="32" height="32" border="0"></td>
			<td class="name">View note</td>
			<td class="required_fav_info">&nbsp;{if isset($fav_tooltip)}{$fav_tooltip}{/if}&nbsp;&nbsp;&nbsp;{if isset($info_tooltip)}{$info_tooltip}{/if}</td>
		</tr>
	</tbody>
</table>


<div class="css3_content_shadow_view">
    <table id="Utils_Attachment__view" border="0" cellpadding="0" cellspacing="5">
    	<tbody>
    		<tr>
    			<td class="header" colspan="3">{$header}</td>
        	</tr>
            <tr>
                <!--<td class="notepad-left">&nbsp;</td>-->
                <td class="note" colspan="2">{$note}</td>
            </tr>
			{if $file!=''}
			<tr>
				<td class="file file_icon">
                    {$__link.file.open}
                        <img src="{$theme_dir}/Utils/Attachment/attach.png" alt="" align="left" border="0" width="32" height="32">
                    {$__link.file.close}
                </td>            
				<td class="file file_name">
                    {$__link.file.open}
                        <span>{$__link.file.text}</span>
                    {$__link.file.close}
                </td>
                <td class="file_desc">File size: {$file_size}<br>Created by: {$upload_by}<br>Created on: {$upload_on}</td>
			</tr>
            {else}
			<tr>
				<td class="file file_icon">&nbsp;</td>            
				<td class="file file_name">&nbsp;</td>
                <td class="file_desc">&nbsp;</td>
			</tr>
			{/if}
    	</tbody>
    </table>
 </div>
