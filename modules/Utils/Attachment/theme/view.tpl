<table class="Utils_Attachment__table" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td class="icon"><img src="{$theme_dir}/Utils_Attachment__icon.png" width="32" height="32" border="0"></td>
			<td class="name">View note</td>
			<td class="required_fav_info">&nbsp;{if isset($fav_tooltip)}{$fav_tooltip}{/if}&nbsp;&nbsp;&nbsp;{if isset($info_tooltip)}{$info_tooltip}{/if}</td>
		</tr>
	</tbody>
</table>


<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="content_shadow">
<!-- -->

    <table id="Utils_Attachment__view" border="0" cellpadding="5" cellspacing="5">
    	<tbody>
    		<tr>
    			<td class="header">{$header}</td>
        	</tr>
            <tr>
                <td class="note">{$note}</td>
            </tr>
			{if $file!=''}
			<tr>
				<td class="file">
                    <div>
                    {$__link.file.open}
                    <img src="{$theme_dir}/Utils_Attachment__attach.png" alt="" align="left" border="0" width="32" height="32">
                    <div style="height: 5px;"></div>
                    <span>{$__link.file.text}</span>
                    {$__link.file.close}
                    </div>
                </td>
			</tr>
			{/if}
    	</tbody>
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
