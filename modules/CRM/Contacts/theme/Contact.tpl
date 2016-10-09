{assign var=count value=0}
{php}
	$this->_tpl_vars['multiselects'] = array();
{/php}
{foreach key=k item=f from=$fields name=fields}
	{if $f.type!="multiselect"}
		{assign var=count value=$count+1}
	{else}
		{php}
			$this->_tpl_vars['multiselects'][] = $this->_tpl_vars['f'];
		{/php}
	{/if}
{/foreach}
{php}
	if ($this->_tpl_vars['action']!='view')
		$this->_tpl_vars['count'] = $this->_tpl_vars['count']+1;
	$this->_tpl_vars['rows'] = ceil($this->_tpl_vars['count']/$this->_tpl_vars['cols']);
	$this->_tpl_vars['mss_rows'] = ceil(count($this->_tpl_vars['multiselects'])/$this->_tpl_vars['cols']);
	$this->_tpl_vars['no_empty'] = $this->_tpl_vars['count']-floor($this->_tpl_vars['count']/$this->_tpl_vars['cols'])*$this->_tpl_vars['cols'];
	if ($this->_tpl_vars['no_empty']==0) $this->_tpl_vars['no_empty'] = $this->_tpl_vars['cols']+1;
	$this->_tpl_vars['mss_no_empty'] = count($this->_tpl_vars['multiselects'])-floor(count($this->_tpl_vars['multiselects'])/$this->_tpl_vars['cols'])*$this->_tpl_vars['cols'];
	if ($this->_tpl_vars['mss_no_empty']==0) $this->_tpl_vars['mss_no_empty'] = $this->_tpl_vars['cols']+1;
	$this->_tpl_vars['cols_percent'] = 100 / $this->_tpl_vars['cols'];
	$this->_tpl_vars['grid_cols'] = 12 / $this->_tpl_vars['cols'];
{/php}

{if $main_page}

<div class="panel panel-default">
	<div class="panel-heading clearfix">
		<div class="pull-left">
			<img alt="&nbsp;" class="icon" src="{$icon}" width="32" height="32" border="0"> <span class="form-inline">{$caption}</span>
		</div>
		<div class="pull-right">
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
	<div class="panel-body">

{if isset($click2fill)}
    {$click2fill}
{/if}

        {/if}
						{* create new company *}
						{if isset($form_data.create_company)}
						<div class="col col-md-12" style="margin-bottom: 10px">
							<div class="label label-default" nowrap>
								{$form_data.create_company.label}
							</div>
							<div style="display: inline-block; margin-left: 10px">
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
							{assign var=y value=1}
						{foreach key=k item=f from=$fields name=fields}
							{if $f.type!="multiselect"}
								{if !isset($focus) && $f.type=="text"}
									{assign var=focus value=$f.element}
								{/if}

										{if $y==1}
											<div class="col col-md-{$grid_cols}">
								{/if}
								{$f.full_field}
								{if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
									{assign var=y value=1}
									{assign var=x value=$x+1}
											</div>
								{else}
									{assign var=y value=$y+1}
								{/if}
							{/if}
						{/foreach}
							</div>
		{if !empty($multiselects)}
								<div>
				{assign var=x value=1}
				{assign var=y value=1}
				{foreach key=k item=f from=$multiselects name=fields}
					{if $y==1}
											<div class="col col-md-{$grid_cols}">
					{/if}
					{$f.full_field}
					{if $y==$mss_rows or ($y==$mss_rows-1 and $x>$mss_no_empty)}
						{assign var=y value=1}
						{assign var=x value=$x+1}
											</div>
					{else}
						{assign var=y value=$y+1}
					{/if}
				{/foreach}
								</div>
		{/if}
							<div>
				{foreach key=k item=f from=$longfields name=fields}
					{$f.full_field}
				{/foreach}
							</div>

						{if $main_page}
{php}
	eval_js('focus_by_id(\'last_name\');');
{/php}
						{/if}

		{if $main_page}
			</div></div>
		{/if}