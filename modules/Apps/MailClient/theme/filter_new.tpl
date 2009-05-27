{$form_open}

<!-- SHADOW BEGIN -->
	<div class="layer" style="padding: 9px; width: 815px;">
		<div class="content_shadow">
<!-- -->

<div style="padding: 2px 2px 2px 2px; background-color: #FFFFFF;">

<table id="Apps_MailClient_new" border="0" cellpadding="0" cellspacing="0">
    <tbody>
        <tr>
            <td class="label" colspan=2>{$form_data.header.general_header}</td>
        </tr>
        <tr>
            <td class="label">{$form_data.name.label}</td>
            <td class="data"><span class="error">{$form_data.name.error}</span>{$form_data.name.html}</td>
        </tr>
        <tr>
            <td class="label">{$form_data.match.label}</td>
            <td class="data"><span class="error">{$form_data.match.error}</span>{$form_data.match.html}</td>
        </tr>
    </tbody>
</table>


<div id="{$rules_block}">
<table style="display:none">
    <tbody id="{$rule_template_block}">
        <tr id="{$rule_remove_block}template">
            <td class="label">
		{$form_data.rule.template.error}
		{$form_data.rule.template.html}
	    </td>
	</tr>
    </tbody>
</table>

<table id="Apps_MailClient_new" border="0" cellpadding="0" cellspacing="0">
    <tbody id="{$rules_elements}">
        <tr>
            <td class="label">{$form_data.header.rules_header}</td>
        </tr>
{foreach item=f from=$rules_ids}
        <tr id="{$rule_remove_block}{$f}">
            <td class="label">
		{$form_data.rule.$f.error}
		{$form_data.rule.$f.html}
	    </td>
        </tr>
{/foreach}
    </tbody>
</table>

{$form_data.add_rule_button.html}

</div>


<table style="display:none">
    <tbody id="{$action_template_block}">
        <tr id="{$action_remove_block}template">
            <td class="label">
		{$form_data.action.template.error}
		{$form_data.action.template.html}
	    </td>
	</tr>
    </tbody>
</table>

<table id="Apps_MailClient_new" border="0" cellpadding="0" cellspacing="0">
    <tbody id="{$actions_elements}">
        <tr>
            <td class="label">{$form_data.header.actions_header}</td>
        </tr>
{foreach item=f from=$actions_ids}
        <tr id="{$action_remove_block}{$f}">
            <td class="label">
		{$form_data.action.$f.error}
		{$form_data.action.$f.html}
	    </td>
        </tr>
{/foreach}
    </tbody>
</table>

{$form_data.add_action_button.html}


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
