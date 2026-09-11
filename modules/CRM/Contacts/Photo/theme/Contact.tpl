{if isset($form_data.paste_company_info)}
	{$form_data.paste_company_info.html}
{/if}

{assign var=count value=0}
{foreach key=k item=f from=$fields name=fields}
	{assign var=count value=$count+1}
{/foreach}
{php}
	if ($this->_tpl_vars['action']!='view')
		$this->_tpl_vars['count'] = $this->_tpl_vars['count']+1;
	$this->_tpl_vars['rows'] = ceil($this->_tpl_vars['count']/$this->_tpl_vars['cols']);
	$this->_tpl_vars['no_empty'] = $this->_tpl_vars['count']-floor($this->_tpl_vars['count']/$this->_tpl_vars['cols'])*$this->_tpl_vars['cols'];
	if ($this->_tpl_vars['no_empty']==0) $this->_tpl_vars['no_empty'] = $this->_tpl_vars['cols']+1;
	$this->_tpl_vars['cols_percent'] = 100 / $this->_tpl_vars['cols'];
{/php}

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


<!-- SHADOW BEGIN -->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="css3_content_shadow">
<!-- -->

<div class="Utils_RecordBrowser__container">

<div class="Utils_RecordBrowser__View_entry">
<div class="epesi-rv-columns">
	<div class="column left-column" style="width: {$cols_percent}%;">
		<div class="{if $action == 'view'}view{else}edit{/if}">
						{* create new company *}
						{if isset($form_data.create_company)}
						<div class="epesi-rv-row">
							<div class="label">
								{$form_data.create_company.label}
							</div>
							<div style="flex: 1 1 auto; min-width: 0;">
								<div class="create-company" style="width:24px; display:inline-block; float: left">
									{$form_data.create_company.html}{if $action == 'view'}&nbsp;{/if}
								</div>
								<div style="display:inline-block;width: calc(100% - 24px)" class="data">
									{if isset($form_data.create_company_name.error)}<span class="error">{$form_data.create_company_name.error}</span>{/if}{$form_data.create_company_name.html}{if $action == 'view'}&nbsp;{/if}
								</div>
							</div>
						</div>
						{/if}
						{assign var=x value=1}
						{if $action=='view'}
							{assign var=y value=1}
						{else}
							{assign var=y value=2}
						{/if}
						{foreach key=k item=f from=$fields name=fields}
							{if $f.element!="login"}
								{if !isset($focus) && $f.type=="text"}
									{assign var=focus value=$f.element}
								{/if}

								{if $y==1 && $x==2}
								</div>
							</div>
							<div class="column" style="flex: 1 1 0; min-width: 0;">
								<div class="{if $action == 'view'}view{else}edit{/if}">
								{/if}
								{$f.full_field}
								{if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
									{assign var=y value=1}
									{assign var=x value=$x+1}
								{else}
									{assign var=y value=$y+1}
								{/if}
							{/if}
						{/foreach}
		</div>
	</div>
	{if $action=='view'}
	<div class="column" style="flex: 0 0 128px;">
		<a class="photo" {$photo_link}>
			<img  class="shadow_5px_left" src="{$photo_src}" >
			{if isset($photo_note)}
				<div class="photo_note">
					{$photo_note}
				</div>
			{/if}
			<div class="overlay">
			</div>
		</a>
	</div>
	{/if}
</div>
{* Multiselect and long text fields, in their shared RecordBrowser field
   order (RecordBrowser_0.php's $secondary_blocks) - see CRM_Contacts'
   Contact.tpl for the full rationale. *}
{foreach key=bk item=block from=$secondary_blocks name=secondary_blocks}
	{if $block.type=='long'}
		<div class="longfields {if $action == 'view'}view{else}edit{/if}">
			{$block.item.full_field}
		</div>
	{else}
		{* Row-major, not column-major: each row of up to $cols fields renders
		   as its own .epesi-rv-columns, left to right, before moving to the
		   next row - so reading order (top to bottom) matches Manage Fields'
		   own order (e.g. Employees, Customers, Related), instead of filling
		   one column all the way down before starting the next. *}
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


{php}
	eval_js('focus_by_id(\'last_name\');');
{/php}


</div>
 		</div>
	</div>
