{* Get total number of fields to display *}
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

	<div class="card ">
		<div class="card-header clearfix">
			<div class="pull-left">
				<i class="pull-left fa fa-{$icon} fa-2x"></i>
				<span class="form-inline">{$caption}</span>
			</div>
			<div class="pull-right">
					{*{$required_note}*}
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
		<div class="card-body">

			{if isset($click2fill)}
				{$click2fill}
			{/if}

{/if}
			<div class="row">
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
				<div class="row">
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
			<div class="row">
						{foreach key=k item=f from=$longfields name=fields}
							<div class="col-xs-12">{$f.full_field}</div>
						{/foreach}
			</div>

		{if $main_page}
			{php}
				if (isset($this->_tpl_vars['focus'])) eval_js('focus_by_id(\''.$this->_tpl_vars['focus'].'\');');
			{/php}
		{/if}

{if $main_page}
		</div>
	</div>
{/if}