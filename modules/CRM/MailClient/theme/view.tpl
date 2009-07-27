<table class="CRM_MailClient__table" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td class="icon"><img src="{$theme_dir}/CRM/MailClient/icon.png" width="32" height="32" border="0"></td>
			<td class="name">View mail</td>
			<td class="required_fav_info">&nbsp;{if isset($info_tooltip)}{$info_tooltip}{/if}</td>
		</tr>
	</tbody>
</table>

<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="content_shadow">
<!-- -->

    <table id="CRM_MailClient__view" border="0" cellpadding="0" cellspacing="5">
    	<tbody>
    		<tr>
    			<td class="header" colspan="3">{$header}</td>
        	</tr>
            <tr>
                <td class="notepad-left2">{$subject_caption}</td>
                <td class="note2" colspan="2">{$subject}</td>
            </tr>
            <tr>
                <td class="notepad-left2">{$attachments_caption}</td>
                <td class="note2" colspan="2">{$attachments}</td>
            </tr>
            <tr>
                <td class="notepad-left">&nbsp;</td>
                <td class="note" colspan="2">{$body}</td>
            </tr>
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
