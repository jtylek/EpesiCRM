{* Get total number of fields to display *}
{foreach key=k item=f from=$fields name=fields}
	{assign var=count value=$smarty.foreach.fields.total}
{/foreach}
{if $count is not even}
	{assign var=rows value=$count+1}
	{assign var=rows value=$rows/2}
{else}
	{assign var=rows value=$count/2}
{/if}
{assign var=x value=0}

<table class="CRM_Contacts__table" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td class="arrow" style="padding-left: 20px;">
				{if isset($prev_record)}
					{$__link.prev_record.open}<img src="{$theme_dir}/images/big_prev.png" width="24" height="16" border="0" style="vertical-align: middle;">{$__link.prev_record.close}
				{/if}
			</td>
			<td class="icon"><img src="{$theme_dir}/CRM/Contacts/companies.png" width="32" height="32" border="0"></td>
			<td class="arrow">
				{if isset($next_record)}
					{$__link.next_record.open}<img src="{$theme_dir}/images/big_next.png" width="24" height="16" border="0" style="vertical-align: middle;">{$__link.next_record.close}
				{/if}
			</td>
			<td class="name">{$caption}</td>
			<td class="required_fav_info">
				&nbsp;*&nbsp;{$required_note}
				{if isset($subscription_tooltip)}
					&nbsp;&nbsp;&nbsp;{$subscription_tooltip}
				{/if}
				{if isset($fav_tooltip)}
					&nbsp;&nbsp;&nbsp;{$fav_tooltip}
				{/if}
				{if isset($info_tooltip)}
					&nbsp;&nbsp;&nbsp;{$info_tooltip}
				{/if}
				{if isset($history_tooltip)}
					&nbsp;&nbsp;&nbsp;{$history_tooltip}
				{/if}
			</td>
		</tr>
	</tbody>
</table>


<!-- SHADOW BEGIN -->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="content_shadow">
<!-- -->

<div style="padding: 2px 2px 2px 2px; background-color: #FFFFFF;">

{* Outside table *}
<table id="CRM_Contacts__View_entry" cellpadding="0" cellspacing="0" border="0">
	<tbody>
		<tr>
			<td class="left-column">
				{* First column table *}
				<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
					<tr>
						{assign var=i value=0}
						{assign var=j value=0}
						{foreach key=k item=f from=$fields name=fields}
							{if !isset($focus) && $f.type=="text"}
								{assign var=focus value=$f.element}
							{/if}
							<td class="label" nowrap>{$f.label}{if $f.required}*{/if}</td>
							<td class="data">{if $f.error}{$f.error}{/if}{$f.html}{if $action == 'view'}&nbsp;{/if}</td>
							{assign var=x value=$x+1}
							{* If more than half records displayed start new table - second column table *}
							{if $x >= $rows and $i==0}
						</tr>
					</table>
				</td>
				{* First table closed - start second column*}
				<td class="right-column right-column-{if $action == 'view'}view{else}edit{/if}">
					<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
						<tr>
							{assign var=i value=1}
							{else}
						</tr>
						<tr>
							{/if}
							{assign var=j value=$j+1}
						{/foreach}
						{* Fill empty row if number of records is not even *}
						{if $j is not even}
							<td class="label">&nbsp;</td>
							<td class="label">&nbsp;</td>
						{/if}
					</tr>
					{if isset($form_data.create_company)}
					<tr>
						<td class="label" nowrap>
							{$form_data.create_company.label}
						</td>
						<td class="data create-company">
							{$form_data.create_company.html}{if $action == 'view'}&nbsp;{/if}
						</td>
					</tr>
					{/if}
				</table>
			</td>
		</tr>
	</tbody>
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
