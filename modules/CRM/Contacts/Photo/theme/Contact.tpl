{if isset($form_data.paste_company_info)}
	{$form_data.paste_company_info.html}
{/if}

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
{/php}

<div class="card ">
	<div class="card-header clearfix">
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
	<div class="card-body">

{if isset($click2fill)}
    {$click2fill}
{/if}


<!-- SHADOW BEGIN -->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="css3_content_shadow">
<!-- -->

<div class="Utils_RecordBrowser__container">

{* Outside table *}
<table class="Utils_RecordBrowser__View_entry" cellpadding="0" cellspacing="0" border="0">
	<tbody>
		<tr>
			<td class="left-column">
				<table border="0" cellpadding="0" cellspacing="0" class="{if $action == 'view'}view{else}edit{/if}">
					<tbody>
						{* create new company *}
						{if isset($form_data.create_company)}
						<tr>
							<td class="label" nowrap>
								{$form_data.create_company.label}
							</td>
							<td>
								<div class="create-company" style="width:24px; display:inline-block; float: left">
									{$form_data.create_company.html}{if $action == 'view'}&nbsp;{/if}
								</div>
								<div style="display:inline-block;width: calc(100% - 24px)" class="data">
									{if isset($form_data.create_company_name.error)}<span class="error">{$form_data.create_company_name.error}</span>{/if}{$form_data.create_company_name.html}{if $action == 'view'}&nbsp;{/if}
								</div>
							</td>
						</tr>
						{else}
							{if $action == 'edit'}
								{* empty *}
{*								<tr style="display:none;">
									<td class="label" align="left">&nbsp;</td>
									<td class="data" colspan="2" align="left">&nbsp;</td>
								</tr>*}
							{/if}
						{/if}
						{assign var=x value=1}
						{if $action=='view'}
							{assign var=y value=1}
						{else}
							{assign var=y value=2}
						{/if}
						{foreach key=k item=f from=$fields name=fields}
							{if $f.type!="multiselect" && $f.element!="login"}
								{if !isset($focus) && $f.type=="text"}
									{assign var=focus value=$f.element}
								{/if}

								{if $y==1 && $x==2}
								<td class="column" {if $action=='view'}style="width: 43%;"{else}style="width: 50%;" colspan="2"{/if}>
									<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
								{/if}
								{$f.full_field}
								{if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
									{if $x>$no_empty}
										<tr style="display:none;">
											<td class="label">&nbsp;</td>
											<td colspan="2" class="data">&nbsp;</td>
										</tr>
									{/if}
									{assign var=y value=1}
									{assign var=x value=$x+1}
									</table>
								</td>
								{else}
									{assign var=y value=$y+1}
								{/if}
							{/if}
						{/foreach}
			{if $action=='view'}
			<td style="width:128px;vertical-align:top;">
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
			</td>
			{/if}
		</tr>
		{if !empty($multiselects)}
			<tr>
				{assign var=x value=1}
				{assign var=y value=1}
				{foreach key=k item=f from=$multiselects name=fields}
					{if $y==1}
					<td class="column" style="width: {$cols_percent}%;" {if $x==2}colspan="2"{/if}>
						<table cellpadding="0" cellspacing="0" border="0" class="multiselects {if $action == 'view'}view{else}edit{/if}" style="border-top: none;">
					{/if}
					{$f.full_field}
					{if $y==$mss_rows or ($y==$mss_rows-1 and $x>$mss_no_empty)}
						{if $x>$mss_no_empty}
							<tr>
								<td class="label">&nbsp;</td>
								<td class="data">&nbsp;</td>
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
		{/if}
		<tr>
			<td colspan="3">
			<table cellpadding="0" cellspacing="0" border="0" class="longfields {if $action == 'view'}view{else}edit{/if}" style="border-top: none;">
				{foreach key=k item=f from=$longfields name=fields}
					{$f.full_field}
				{/foreach}
			</table>
			</td>
		</tr>
	</tbody>
</table>


{php}
	eval_js('focus_by_id(\'last_name\');');
{/php}


</div>
 		</div>
	</div>

		{if $main_page}
	</div>
</div>
{/if}