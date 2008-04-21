{* Get total number of fields to display *}
{assign var=count value=0}
{foreach key=k item=f from=$fields name=fields}
	{assign var=count value=$smarty.foreach.fields.total}
{/foreach}
{php}
	$this->_tpl_vars['rows'] = ceil($this->_tpl_vars['count']/$this->_tpl_vars['cols']);
	$this->_tpl_vars['no_empty'] = $this->_tpl_vars['count']-floor($this->_tpl_vars['count']/$this->_tpl_vars['cols'])*$this->_tpl_vars['cols'];
	if ($this->_tpl_vars['no_empty']==0) $this->_tpl_vars['no_empty'] = $this->_tpl_vars['cols']+1;
	$this->_tpl_vars['cols_percent'] = 100 / $this->_tpl_vars['cols'];
{/php}

{if $main_page}
<table class="Utils_RecordBrowser__table" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td class="icon"><img src="{$icon}" width="32" height="32" border="0"></td>
			<td class="name">{$caption}</td>
			<td class="required_fav_info">
				&nbsp;*&nbsp;{$required_note}
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
			{assign var=x value=1}
			{assign var=y value=1}
			{foreach key=k item=f from=$fields name=fields}
			{if !isset($focus) && $f.type=="text"}
				{assign var=focus value=$f.element}
			{/if}

			{if $y==1}
			<td class="column" style="width: {$cols_percent}%;">
				<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
			{/if}
					<tr>
						<td class="label">{$f.label}{if $f.required}*{/if}</td>
						<td class="data {$f.style}">{if $f.error}{$f.error}{/if}{$f.html}{if $action == 'view'}&nbsp;{/if}</td>
					</tr>
			{if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
				{if $x>$no_empty}
					<tr>
						<td class="label">&nbsp;</td>
						<td class="label">&nbsp;</td>
					</tr>
				{/if}
				{assign var=y value=1}
				{assign var=x value=$x+1}
				</table>
			</td>
			{else}
				{assign var=y value=$y+1}
			{/if}

			{/foreach}
		</tr>
		<tr>
			<td colspan="2">
			<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}" style="border-top: none;">
				<tr>
					{foreach key=k item=f from=$longfields name=fields}
						<td class="label long_label">{$f.label}{if $f.required}*{/if}</td>
						<td class="data long_data {if $f.type == 'currency'}currency{/if}">{if $f.error}{$f.error}{/if}{$f.html}{if $action == 'view'}&nbsp;{/if}</td>
					{/foreach}
				</tr>
			</table>
			</td>
		</tr>
	</tbody>
</table>

{if $main_page}
{php}
	if (isset($this->_tpl_vars['focus'])) eval_js('focus_by_id(\''.$this->_tpl_vars['focus'].'\');');
{/php}
{/if}

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
