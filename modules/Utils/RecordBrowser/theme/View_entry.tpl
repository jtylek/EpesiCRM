{* Get total number of fields to display *}
{assign var=count value=0}
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

{if $main_page}
<table class="Utils_RecordBrowser__table" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td class="icon"><img src="{$icon}" width="32" height="32" border="0"></td>
			<td class="name">{$caption}</td>
			<td class="required_fav_info">&nbsp;*&nbsp;{$required_note}&nbsp;&nbsp;&nbsp;{if isset($fav_tooltip)}{$fav_tooltip}{/if}&nbsp;&nbsp;&nbsp;{if isset($info_tooltip)}{$info_tooltip}{/if}</td>
		</tr>
	</tbody>
</table>
{/if}

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
						<td class="label">{$f.label}{if $f.required}*{/if}</td>
						<td class="data">{if $f.error}{$f.error}{/if}{$f.html}</td>
						{assign var=x value=$x+1}
						{* If more than half records displayed start new table - second column table *}
						{if $x >= $rows and $i==0}
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
					
				</table>
			</td>		
		</tr>
		<tr>
			<td colspan="2">
			<table cellpadding="0" cellspacing="0" border="0">
				<tr>
					{foreach key=k item=f from=$longfields name=fields}
						<td class="label long_label">{$f.label}{if $f.required}*{/if}</td>
						<td class="data long_data">{if $f.error}{$f.error}{/if}{$f.html}</td>
					{/foreach}
				</tr>
			</table>
			</td>
		</tr>
	</tbody>
</table>

{php}
	if (isset($this->_tpl_vars['focus'])) eval_js('focus_by_id(\''.$this->_tpl_vars['focus'].'\');');
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
