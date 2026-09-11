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

<div id="CRM_PhoneCall">

	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="css3_content_shadow">

<div class="Utils_RecordBrowser__container">
<div class="Utils_RecordBrowser__View_entry">
<div class="epesi-rv-columns">
	{* LEFT column *}
	<div class="column" style="width: 50%;">
		{* subject *}
		<div class="{if $action == 'view'}view{else}edit{/if}">
			<div class="epesi-rv-row">
				<div class="label">{$form_data.subject.label}{if $form_data.subject.required}*{/if}</div>
				<div class="data" id="_subject__data">
					<span class="error">{$form_data.subject.error}</span>
					{$form_data.subject.html}
				</div>
			</div>
			{if $action == 'view'}
					<div class="epesi-rv-row">
						<div class="label">{$form_data.customer.label}</div>
						<div class="data" id="_customer__data_mod">
							<span class="error">
								{$form_data.customer.error}
							</span>
							{if $raw_data.other_customer}{$form_data.other_customer_name.html}{else}{$form_data.customer.html}{/if}&nbsp;
						</div>
					</div>
					<div class="epesi-rv-row">
						<div class="label">{$form_data.phone.label}</div>
						<div class="data" id="_phone__data_mod">
							<span class="error">
								{$form_data.phone.error}
							</span>
							{if $raw_data.other_phone}{$form_data.other_phone_number.html}{else}{$form_data.phone.html}{/if}&nbsp;
						</div>
					</div>
			{else}
					<div class="epesi-rv-row">
						{* Not driven by $form_data.customer.required (never set true - see
						   PhoneCallInstall.php): Customer/Phone are only conditionally
						   required (the Other Customer/Other Phone checkbox below is a
						   valid alternative, enforced in CRM_PhoneCallCommon::
						   submit_phonecall()'s own form rule, not RecordBrowser's schema
						   requiredness), and that flag also drives an unconditional
						   "always required" QuickForm rule that would break the escape
						   hatch. The asterisk here is a hardcoded hint matching the *real*
						   requirement instead. *}
						<div class="label">{$form_data.customer.label}*</div>
						<div class="data" id="_customer__data">
							<span class="error">
								{$form_data.customer.error}
							</span>
							{$form_data.customer.html}{if $action == 'view'}&nbsp;{/if}
						</div>
					</div>
					<div class="epesi-rv-row">
						<div class="label">{$form_data.other_customer.label}{if $form_data.other_customer.required}*{/if}</div>
						<div style="flex: 1 1 auto; min-width: 0; display: flex;">
							<div id="_other_customer__data">
								{$form_data.other_customer.html}
							</div>
							<div class="data" style="flex: 1 1 auto; min-width: 0;" id="_other_customer_name__data">
								<span class="error">
									{$form_data.other_customer_name.error}
								</span>
								{$form_data.other_customer_name.html}{if $action == 'view'}&nbsp;{/if}
							</div>
						</div>
					</div>
					<div class="epesi-rv-row">
						{* See the Customer field above - same reasoning, hardcoded
						   asterisk rather than $form_data.phone.required. *}
						<div class="label">{$form_data.phone.label}*</div>
						<div class="data" id="_phone__data">
							<span class="error">
								{$form_data.phone.error}
							</span>
							{$form_data.phone.html}{if $action == 'view'}&nbsp;{/if}
						</div>
					</div>
					<div class="epesi-rv-row">
						<div class="label">{$form_data.other_phone.label}{if $form_data.other_phone.required}*{/if}</div>
						<div style="flex: 1 1 auto; min-width: 0; display: flex;">
							<div id="_other_phone__data">
								{$form_data.other_phone.html}
							</div>
							<div class="data" style="flex: 1 1 auto; min-width: 0;" id="_other_phone_number__data">
								<span class="error">
									{$form_data.other_phone_number.error}
								</span>
								{$form_data.other_phone_number.html}{if $action == 'view'}&nbsp;{/if}
							</div>
						</div>
					</div>
			{/if}
		</div>
	</div>
	{* RIGHT column *}
	<div class="column" style="width: 50%;">
		<div class="{if $action == 'view'}view{else}edit{/if}">
			{$fields.date_and_time.full_field}
			{$fields.status.full_field}
			{$fields.permission.full_field}
			{$fields.priority.full_field}
			{foreach key=k item=f from=$fields name=fields}
				{if (	$k!='subject' &&
						$k!='company_name' &&
						$k!='employees' &&
						$k!='related_to' &&
						$k!='status' &&
						$k!='priority' &&
						$k!='permission' &&
						$k!='customer' &&
						$k!='other_customer' &&
						$k!='other_customer_name' &&
						$k!='phone' &&
						$k!='other_phone' &&
						$k!='other_phone_number' &&
						$k!='date_and_time')}
					{$f.full_field}
				{/if}
			{/foreach}
		</div>
	</div>
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
</div>




{php}
	eval_js('focus_by_id(\'subject\');');
{/php}

<!-- SHADOW END -->
 		</div>
	</div>
<!-- -->

</div>

