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
	$this->_tpl_vars['count'] = $this->_tpl_vars['count']+1;

	$this->_tpl_vars['rows'] = ceil($this->_tpl_vars['count']/$this->_tpl_vars['cols']);
	$this->_tpl_vars['mss_rows'] = ceil(count($this->_tpl_vars['multiselects'])/$this->_tpl_vars['cols']);
	$this->_tpl_vars['no_empty'] = $this->_tpl_vars['count']-floor($this->_tpl_vars['count']/$this->_tpl_vars['cols'])*$this->_tpl_vars['cols'];
	if ($this->_tpl_vars['no_empty']==0) $this->_tpl_vars['no_empty'] = $this->_tpl_vars['cols']+1;
	$this->_tpl_vars['mss_no_empty'] = count($this->_tpl_vars['multiselects'])-floor(count($this->_tpl_vars['multiselects'])/$this->_tpl_vars['cols'])*$this->_tpl_vars['cols'];
	if ($this->_tpl_vars['mss_no_empty']==0) $this->_tpl_vars['mss_no_empty'] = $this->_tpl_vars['cols']+1;
	$this->_tpl_vars['cols_percent'] = 100 / $this->_tpl_vars['cols'];
{/php}

<table class="CRM_Contacts__table" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td class="arrow" style="padding-left: 20px;">
				{if isset($prev_record)}
					{$__link.prev_record.open}<img src="{$theme_dir}/images/big_prev.png" width="24" height="16" border="0" style="vertical-align: middle;">{$__link.prev_record.close}
				{/if}
			</td>
			<td class="icon"><img src="{$theme_dir}/CRM/Contacts/contacts.png" width="32" height="32" border="0"></td>
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
				{if isset($clipboard_tooltip)}
					&nbsp;&nbsp;&nbsp;{$clipboard_tooltip}
				{/if}
				{if isset($history_tooltip)}
					&nbsp;&nbsp;&nbsp;{$history_tooltip}
				{/if}
				{if isset($__link.new_event.open)}
					&nbsp;&nbsp;&nbsp;{$new_event}
				{/if}
				{if isset($__link.new_task.open)}
					&nbsp;&nbsp;&nbsp;{$new_task}
				{/if}
				{if isset($__link.new_phonecall.open)}
					&nbsp;&nbsp;&nbsp;{$new_phonecall}
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
<table id="CRM_Contacts__Contact" cellpadding="0" cellspacing="0" border="0">
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
							<td class="data create-company" style="width:1px">
								{$form_data.create_company.html}{if $action == 'view'}&nbsp;{/if}
							</td>
							<td class="data">
								{if isset($form_data.create_company_name.error)}<span class="error">{$form_data.create_company_name.error}</span>{/if}{$form_data.create_company_name.html}{if $action == 'view'}&nbsp;{/if}
							</td>
						</tr>
						{else}
							{if $action == 'edit'}
								{* empty *}
								<tr>
									<td class="label" align="left">&nbsp;</td>
									<td class="data" colspan="2" align="left">&nbsp;</td>
								</tr>
							{/if}
						{/if}
						{* login *}
						<tr>
							<td class="label" align="left">{$form_data.login.label}</td>
							{if isset($form_data.create_new_user)}
								<td class="data create-company" style="width:1px" align="left">{$form_data.create_new_user.html}</td>
							{/if}
							<td class="data" {if !isset($form_data.create_new_user)}colspan="2" {/if}align="left">{if isset($form_data.login.error)}<span class="error">{$form_data.login.error}</span>{/if}{$form_data.login.html}{if isset($form_data.new_login)}{$form_data.new_login.html}{/if}</td>
						</tr>
						{assign var=x value=1}
						{assign var=y value=3}
						{foreach key=k item=f from=$fields name=fields}
							{if $f.type!="multiselect" && $f.element!="login"}
								{if !isset($focus) && $f.type=="text"}
									{assign var=focus value=$f.element}
								{/if}

								{if $y==1}
								<td class="column" style="width: {$cols_percent}%;">
									<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
								{/if}
										<tr>
											<td class="label">{$f.label}{if $f.required}*{/if}</td>
											<td colspan="2" class="data {$f.style}" id="_{$f.element}__data">{if $f.error}{$f.error}{/if}{$f.html}{if $action == 'view'}&nbsp;{/if}</td>
										</tr>
								{if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
									{if $x>$no_empty}
										<tr>
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
		</tr>
		{if !empty($multiselects)}
			<tr>
				{assign var=x value=1}
				{assign var=y value=1}
				{foreach key=k item=f from=$multiselects name=fields}
					{if $y==1}
					<td class="column" style="width: {$cols_percent}%;">
						<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}" style="border-top: none;">
					{/if}
							<tr>
								<td class="label">{$f.label}{if $f.required}*{/if}{$f.advanced}</td>
								<td class="data {$f.style}">{if isset($f.error)}{$f.error}{/if}{$f.html}{if $action == 'view'}&nbsp;{/if}</td>
							</tr>
					{if $y==$mss_rows or ($y==$mss_rows-1 and $x>$mss_no_empty)}
						{if $x>$mss_no_empty}
							<tr>
								<td class="label">!&nbsp;</td>
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
			<td colspan="2">
			<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}" style="border-top: none;">
				{foreach key=k item=f from=$longfields name=fields}
					<tr>
						<td class="label long_label">{$f.label}{if $f.required}*{/if}</td>
						<td class="data long_data {if $f.type == 'currency'}currency{/if}">{if $f.error}{$f.error}{/if}{$f.html}{if $action == 'view'}&nbsp;{/if}</td>
					</tr>
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
