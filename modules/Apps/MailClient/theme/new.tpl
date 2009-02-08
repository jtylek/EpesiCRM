{$form_open}

<table class="Apps_MailClient_new_header" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td class="arrow" style="padding-left: 20px;">&nbsp;</td>
			<td class="icon"><img src="{$theme_dir}/Apps/MailClient/new.png" width="32" height="32" border="0"></td>
			<td class="arrow">&nbsp;</td>
			<td class="name">{$form_data.header.mail_header}</td>
			<td class="required_fav_info">&nbsp;</td>
		</tr>
	</tbody>
</table>

<!-- SHADOW BEGIN -->
	<div class="layer" style="padding: 9px; width: 815px;">
		<div class="content_shadow">
<!-- -->

<div style="padding: 2px 2px 2px 2px; background-color: #FFFFFF;">

<table id="Apps_MailClient_new" border="0" cellpadding="0" cellspacing="0">
    <tbody>
        <tr>
            <td class="label">{$form_data.from_addr.label}</td>
            <td class="data" colspan="3"><span class="error">{$form_data.from_addr.error}</span>{$form_data.from_addr.html}</td>
        </tr>
        <tr>
            <td class="label">{$form_data.to_addr.label}</td>
            <td class="data"><span class="error">{$form_data.to_addr.error}</span>{$form_data.to_addr.html}</td>
            <td class="buttons">{$addressbook}</td>
            <td class="buttons2">{if isset($addressbook)}{$addressbook_add_button}{/if}</td>
        </tr>
        {if isset($addressbook)}
        <tr>
            <td colspan="4">
                <div id="{$addressbook_area_id}">
                    {$form_data.to_addr_ex.html}
                </div>
            </td>
        </tr>
        {/if}
        <tr>
            <td class="label">{$form_data.subject.label}</td>
            <td class="data" colspan="3"><span class="error">{$form_data.subject.error}</span>{$form_data.subject.html}</td>
        </tr>
        <tr>
            <td class="label" colspan="4">{$form_data.body.label}</td>
        </tr>
        <tr>
            <td class="data" colspan="4"><span class="error">{$form_data.body.error}</span>{$form_data.body.html}</td>
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

{$form_close}
