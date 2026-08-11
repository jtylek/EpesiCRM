{* CRM_PhoneCall registers this as the per-table template for the
   'phonecall' record type (PhoneCallInstall.php calls
   Utils_RecordBrowserCommon::set_tpl('phonecall', ...)) - same bypass-of-
   View_entry.tpl mechanism as CRM_Contacts's Contact.tpl (see
   ../../Contacts/theme_adminlte/Contact.tpl and [[adminlte-theme-incomplete]]
   memory) - only ever selected when $main_page is true, so no {if $main_page}
   guard needed around the header. Icon+caption dropped from the header, same
   as View_entry.tpl/Contact.tpl; tooltips row kept. Layout below (subject/
   customer/phone/date/status/priority, multiselects, longfields) now mirrors
   View_entry.tpl's fluid CSS multi-column container (.epesi-rv-fluid) rather
   than the fixed 50/50 .epesi-rv-columns/.column split this template used
   before - per request, that hardcoded split's own inter-column padding read
   as an unwanted vertical "separator" down the middle of the form, and it
   didn't collapse to one column on a narrow screen the way every other
   RecordBrowser table's own record view already does. A genuine single
   column (no reflow at all) was tried in between, but per request large
   screens still need a 2-column layout - .epesi-rv-fluid's column-width:420px
   already gives that (2 columns wide, 1 column once the viewport is too
   narrow for two), it's just driven by the browser's own reflow instead of a
   hardcoded 50/50 PHP split, matching every other record type's own screen
   instead of a one-off. Real per-field content is unchanged, only the
   wrapper markup. id="CRM_PhoneCall" is kept on the wrapper (unlike
   Contact.tpl, this module's own default theme CSS - modules/CRM/PhoneCall/
   theme/default.css - scopes status/priority/permission dropdown icon
   backgrounds off this id; no theme_adminlte override exists for that file,
   so it still resolves via the normal fallback-to-default mechanism and
   needs the id present to keep matching). View_entry.css (loaded alongside
   any custom $tpl by RecordBrowser_0.php) already covers .label/.data/
   .epesi-rv-fluid/etc, so no separate CSS needed here. *}
{php}
	$this->_tpl_vars['multiselects'] = array();
{/php}
{foreach key=k item=f from=$fields name=fields}
	{if $f.type=="multiselect"}
		{php}
			$this->_tpl_vars['multiselects'][] = $this->_tpl_vars['f'];
		{/php}
	{/if}
{/foreach}

<div class="epesi-rv-header">
	<div class="epesi-rv-tools">
		{if $required_note}*&nbsp;{$required_note}{/if}
		{if isset($subscription_tooltip)}
			{$subscription_tooltip}
		{/if}
		{if isset($fav_tooltip)}
			{$fav_tooltip}
		{/if}
		{if isset($info_tooltip)}
			{$info_tooltip}
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

<div id="CRM_PhoneCall">

<div class="epesi-rv-card card">
	<div class="card-body p-0">

<div class="Utils_RecordBrowser__container">
<div class="Utils_RecordBrowser__View_entry">
<div class="epesi-rv-fluid {if $action == 'view'}view{else}edit{/if}">
	{* subject *}
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
				$k!='date_and_time' &&
                            $f.type != 'multiselect')}
			{$f.full_field}
		{/if}
	{/foreach}
</div>
{if !empty($multiselects)}
	<div class="epesi-rv-fluid multiselects {if $action == 'view'}view{else}edit{/if}">
		{foreach key=k item=f from=$multiselects name=fields}
			{$f.full_field}
		{/foreach}
	</div>
{/if}
<div class="longfields {if $action == 'view'}view{else}edit{/if}">
	{foreach key=k item=f from=$longfields name=fields}
		{$f.full_field}
	{/foreach}
</div>
</div>
</div>

{php}
	eval_js('focus_by_id(\'subject\');');
{/php}

	</div>
</div>

</div>
