{* Get total number of fields to display *}
{assign var=count value=0}
{foreach key=k item=f from=$fields name=fields}
	{assign var=count value=$count+1}
{/foreach}
{php}
	$this->_tpl_vars['rows'] = ceil($this->_tpl_vars['count']/$this->_tpl_vars['cols']);
	$this->_tpl_vars['no_empty'] = $this->_tpl_vars['count']-floor($this->_tpl_vars['count']/$this->_tpl_vars['cols'])*$this->_tpl_vars['cols'];
	if ($this->_tpl_vars['no_empty']==0) $this->_tpl_vars['no_empty'] = $this->_tpl_vars['cols']+1;
	$this->_tpl_vars['cols_percent'] = 100 / $this->_tpl_vars['cols'];
{/php}

{if $main_page}
<div class="Utils_RecordBrowser__table">
	<div class="Utils_RecordBrowser__table_row">
		<div class="Utils_RecordBrowser__table_icon">
			<div class="name">
				<img alt="&nbsp;" class="icon" src="{$icon}" width="32" height="32" border="0">
				<div class="label">{$caption}</div>
			</div>
		</div>
		<div class="required_fav_info">
			{if $required_note}&nbsp;*&nbsp;{$required_note}{/if}
			{if isset($subscription_tooltip)}
				&nbsp;&nbsp;&nbsp;{$subscription_tooltip}
			{/if}
			{if isset($fav_tooltip)}
				&nbsp;&nbsp;&nbsp;{$fav_tooltip}
			{/if}
			{if isset($info_tooltip)}
				&nbsp;&nbsp;&nbsp;{$info_tooltip}
			{/if}
			{if isset($clipboard_tooltip)}
				&nbsp;&nbsp;&nbsp;{$clipboard_tooltip}
			{/if}
			{if isset($history_tooltip)}
				&nbsp;&nbsp;&nbsp;{$history_tooltip}
			{/if}
			{if isset($new)}
				{foreach item=n from=$new}
					&nbsp;&nbsp;&nbsp;{$n}
				{/foreach}
			{/if}
		</div>
	</div>
</div>

{if isset($click2fill)}
    {$click2fill}
{/if}

{/if}

	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="css3_content_shadow">

<div class="Utils_RecordBrowser__container">

<div class="Utils_RecordBrowser__View_entry email">
<div class="epesi-rv-columns">
	{assign var=x value=1}
	{assign var=y value=1}
	{foreach key=k item=f from=$fields name=fields}
		{if !isset($focus) && $f.type=="text"}
			{assign var=focus value=$f.element}
		{/if}

		{if $y==1}
		<div class="column" style="width: {$cols_percent}%;">
			<div class="{if $action == 'view'}view{else}edit{/if}">
		{/if}
		{$f.full_field}
		{if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
			{assign var=y value=1}
			{assign var=x value=$x+1}
			</div>
		</div>
		{else}
			{assign var=y value=$y+1}
		{/if}
	{/foreach}
</div>
{* Multiselect and long text fields, in their shared RecordBrowser field
   order (RecordBrowser_0.php's $secondary_blocks) - a long text field moved
   above/below a multiselect field in Manage Fields is respected here
   instead of long text always trailing every multiselect. The email body
   field is rendered elsewhere in this template/module, so it's skipped here
   same as it always was skipped from the old $longfields loop. *}
{foreach key=bk item=block from=$secondary_blocks name=secondary_blocks}
	{if $block.type=='long'}
		{if $block.item.element!="body"}
			<div class="longfields {if $action == 'view'}view{else}edit{/if}">
				<div class="epesi-rv-row">
					<div class="label long_label">{$block.item.label}{if $block.item.required}*{/if}</div>
				</div>
				<div class="epesi-rv-row">
					<div class="data long_data {if $block.item.type == 'currency'}currency{/if}" id="_{$block.item.element}__data">
						{if $block.item.error}{$block.item.error}{/if}
						{if $block.item.help}
							<div class="help"><img src="{$block.item.help.icon}" alt="help" {$block.item.help.text}></div>
						{/if}
						<div>
							{$block.item.html}{if $action == 'view'}&nbsp;{/if}
						</div>
					</div>
				</div>
			</div>
		{/if}
	{else}
		{* Row-major, not column-major: each row of up to $cols fields renders
		   as its own .epesi-rv-columns, left to right, before moving to the
		   next row - so reading order (top to bottom) matches Manage Fields'
		   own order, instead of filling one column all the way down before
		   starting the next. *}
		{php}
			$this->_tpl_vars['mss_block_grid'] = array_chunk($this->_tpl_vars['block']['items'], $this->_tpl_vars['cols'], true);
		{/php}
		{foreach key=rk item=mss_row from=$mss_block_grid name=secondary_block_rows}
			<div class="epesi-rv-columns">
				{foreach key=k item=f from=$mss_row name=secondary_block_row_items}
					<div class="column" style="width: {$cols_percent}%;">
						<div class="multiselects {if $action == 'view'}view{else}edit{/if}">
							{$f.full_field}
						</div>
					</div>
				{/foreach}
			</div>
		{/foreach}
	{/if}
{/foreach}
</div>

{if $main_page}
{php}
	if (isset($this->_tpl_vars['focus'])) eval_js('focus_by_id(\''.$this->_tpl_vars['focus'].'\');');
{/php}
{/if}

</div>

 		</div>
	</div>
