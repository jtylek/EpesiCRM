{if isset($Form_data.paste_company_info)}
{$Form_data.paste_company_info.html}
{/if}
{assign var=x value=0}

<table class="Utils_RecordBrowser__table" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td class="icon"><img src="{$theme_dir}/CRM_Contacts__contacts.png" width="32" height="32" border="0"></td>
			<td class="name">Contacts</td>
			<td class="required">* {$required_note}</td>
			<td class="fav">{if isset($fav_tooltip)}{$fav_tooltip}{/if}</td>
			<td class="info">{if isset($info_tooltip)}{$info_tooltip}{/if}</td>
		</tr>
	</tbody>
</table>


<!-- SHADOW BEGIN -->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="content_shadow">
<!-- -->

<div style="padding: 4px 5px 5px 5px; background-color: #FFFFFF;">

<table id="Utils_RecordBrowser__View_entry" cellpadding="0" cellspacing="0" border="0">
	<tr>
		{foreach key=k item=f from=$fields}
			{if $x==2}
				</tr><tr>
				{assign var=x value=0}
			{/if}
			{if !isset($focus) && $f.type=="text"}
				{assign var=focus value=$f.element}
			{/if}
			<td class="label" nowrap>{$f.label}{if $f.required}*{/if}</td>
			<td class="data">{if $f.error}{$f.error}{/if}{$f.html}</td>
			{assign var=x value=$x+1}
		{/foreach}
		{assign var=z value=$x*-2+4}
		{section name=y loop=$z}
			<td class="label">&nbsp;</td>
		{/section}
	</tr>
	{if isset($Form_data.create_company)}
	<tr>
		<td class="label" nowrap>
			{$Form_data.create_company.label}
		</td>
		<td class="data">
			{$Form_data.create_company.html}
		</td>
		<td />
	</tr>
	{/if}
</table>
{php}
	eval_js('focus_by_id(\''.$this->_tpl_vars['focus'].'\');');
{/php}

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
