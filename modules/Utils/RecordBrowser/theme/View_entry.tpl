{assign var=x value=0}

<table class="Utils_RecordBrowser__table" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			{* <td class="icon"><img src="{$theme_dir}/icon.png" width="32" height="32" border="0"></td> *}
			<td class="name">View Entry</td>
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

<div style="padding: 2px 2px 2px 2px; background-color: #FFFFFF;">

{* Outside table *}
<table id="Utils_RecordBrowser__View_entry" cellpadding="0" cellspacing="0" border="0">
	<tbody>
		<tr>
			<td class="left-column">
				{* First column table *}
				<table cellpadding="0" cellspacing="0" border="0">
					<tr>
						{assign var=i value=0}
						{assign var=j value=0}
						{foreach key=k item=f from=$fields name=fields}
						{if !isset($focus) && $f.type=="text"}
							{assign var=focus value=$f.element}
						{/if}
						<td class="label" nowrap>{$f.label}{if $f.required}*{/if}</td>
						<td class="data">{if $f.error}{$f.error}{/if}{$f.html}</td>
						{assign var=x value=$x+1}
						{* If more than half records displayed start new table - second column table *}
						{if ($smarty.foreach.fields.index+1 > $smarty.foreach.fields.total/2) and $i==0}
					</tr>
				</table>
			</td>
			{* First table closed - start second column*}
			<td class="right-column">
				<table cellpadding="0" cellspacing="0" border="0">
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
					{if isset($Form_data.create_company)}
					<tr>
						<td class="label" nowrap>
							{$Form_data.create_company.label}
						</td>
						<td class="data">
							{$Form_data.create_company.html}
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
