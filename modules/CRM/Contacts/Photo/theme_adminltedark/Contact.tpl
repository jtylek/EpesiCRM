{* CRM_Contacts_Photo overrides the same 'contact' tpl registration as the
   base CRM_Contacts module (both call Utils_RecordBrowserCommon::set_tpl
   ('contact', ...) in their respective Install.php - whichever runs last at
   install time wins in the DB), adding a photo box column and a
   paste_company_info block on top of the base layout. Same treatment as
   ../../theme_adminlte/Contact.tpl (flex - .epesi-rv-columns/.column/.view/
   .edit/.epesi-rv-row/.label/.data instead of a <table> of <table>s):
   module icon+caption header dropped, tooltips row kept, field content
   unchanged. The photo column (view mode only, 128px) and the second field
   column simply become flex siblings with the second column set to
   flex:1 1 0 (fills whatever space is left after the first column and the
   optional photo box) - the original table version needed width:43%/50%
   plus colspan gymnastics to approximate this; flex absorbs the remaining
   space natively, no colspan equivalent needed. No separate CSS needed -
   View_entry.css is loaded alongside any custom $tpl by RecordBrowser_0.php
   and already covers these shared class names. *}
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

<div class="epesi-rv-header">
	<div class="epesi-rv-tools">
		*&nbsp;{$required_note}
		{if isset($subscription_tooltip)}
			{$subscription_tooltip}
		{/if}
		{if isset($fav_tooltip)}
			{$fav_tooltip}
		{/if}
		{if isset($info_tooltip)}
			{$info_tooltip}
		{/if}
		{if isset($clipboard_tooltip)}
			{$clipboard_tooltip}
		{/if}
		{if isset($history_tooltip)}
			{$history_tooltip}
		{/if}
		{if isset($new)}
			{foreach item=n from=$new}
				{$n}
			{/foreach}
		{/if}
	</div>
</div>

{if isset($click2fill)}
    {$click2fill}
{/if}

<div class="epesi-rv-card card">
	<div class="card-body p-0">

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
							<div style="flex: 1 1 auto; min-width: 0; display: flex;">
								<div class="create-company">
									{$form_data.create_company.html}{if $action == 'view'}&nbsp;{/if}
								</div>
								<div class="data" style="flex: 1 1 auto; min-width: 0;">
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
							{if $f.type!="multiselect" && $f.element!="login"}
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
{if !empty($multiselects)}
	<div class="epesi-rv-columns">
		{assign var=x value=1}
		{assign var=y value=1}
		{foreach key=k item=f from=$multiselects name=fields}
			{if $y==1}
			<div class="column" style="width: {$cols_percent}%;">
				<div class="multiselects {if $action == 'view'}view{else}edit{/if}">
			{/if}
			{$f.full_field}
			{if $y==$mss_rows or ($y==$mss_rows-1 and $x>$mss_no_empty)}
				{assign var=y value=1}
				{assign var=x value=$x+1}
				</div>
			</div>
			{else}
				{assign var=y value=$y+1}
			{/if}
		{/foreach}
	</div>
{/if}
<div class="longfields {if $action == 'view'}view{else}edit{/if}">
	{foreach key=k item=f from=$longfields name=fields}
		{$f.full_field}
	{/foreach}
</div>
</div>

{php}
	eval_js('focus_by_id(\'last_name\');');
{/php}

</div>

	</div>
</div>
