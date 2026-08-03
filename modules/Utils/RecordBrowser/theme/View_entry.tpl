{* Split multiselects out from the regular fields - long text fields are
   already kept separate by RecordBrowser_0.php itself ($longfields). No
   row/column pre-computation anymore: the fluid CSS multi-column container
   below (.epesi-rv-fluid) lets the browser decide how many columns fit,
   based on available width, instead of a fixed PHP-computed count. *}
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

{if $main_page}
<table class="Utils_RecordBrowser__table" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td style="width:100px;">
				<div class="name">
					<img alt="&nbsp;" class="icon" src="{$icon}" width="32" height="32" border="0">
					<div class="label">{$caption}</div>
				</div>
			</td>
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
				{if isset($new)}
					{foreach item=n from=$new}
						&nbsp;&nbsp;&nbsp;{$n}
					{/foreach}
				{/if}
			</td>
		</tr>
	</tbody>
</table>

{if isset($click2fill)}
    {$click2fill}
{/if}

{/if}

	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="css3_content_shadow">

<div class="Utils_RecordBrowser__container">

{* Field grid - was a <table> of <table>s with a PHP-computed fixed column
   count (RecordBrowser_0.php::view_entry_details()'s old $cols param); now a
   flat sequence of divs inside a CSS multi-column container so the browser
   picks however many columns fit the available width. Built directly from
   each field's raw pieces ($f.label/.html/.error/.help/etc, all provided by
   get_field_display_options() alongside the pre-rendered $f.full_field) -
   NOT through single_field.tpl, which still emits <tr>/<td> and is shared
   with Contact.tpl/mails.tpl/PhoneCall's and Meeting's default.tpl/
   Attachment's View_entry.tpl, all of which keep their existing fixed-column
   table layout unchanged (see AI-shared/bug-patterns.md - a <tr>/<td>
   dropped directly into a <div> is invalid HTML that browsers silently
   relocate). Reuses the existing .label/.data/.form_error/.automulti/etc
   classes and CSS directly - all already unscoped from any table ancestor
   requirement - except a handful of view/edit-mode-specific rules that
   *were* table.view/table.edit-scoped, which have an equivalent added under
   .epesi-rv-fluid.view/.edit in View_entry.css instead. *}
<div class="Utils_RecordBrowser__View_entry">
<div class="epesi-rv-fluid {if $action == 'view'}view{else}edit{/if}">
	{foreach key=k item=f from=$fields name=fields}
		{if $f.type!="multiselect"}
			{if !isset($focus) && $f.type=="text"}
				{assign var=focus value=$f.element}
			{/if}
			<div class="epesi-rv-row">
				<div class="label{if $f.type == 'long text'} long_label{/if}">{$f.label}{if $f.required}*{/if}{$f.advanced}</div>
				<div class="data{if $f.type == 'long text'} long_data{/if} {$f.style}" id="_{$f.element}__data">
					{if $f.error}{$f.error}{/if}
					{if $f.help}
						<div class="help"><img src="{$f.help.icon}" alt="help" {$f.help.text}></div>
					{/if}
					<div>
						{$f.html}{if $action == 'view'}&nbsp;{/if}
					</div>
				</div>
			</div>
		{/if}
	{/foreach}
</div>
{if !empty($multiselects)}
	<div class="epesi-rv-fluid multiselects {if $action == 'view'}view{else}edit{/if}">
		{foreach key=k item=f from=$multiselects name=fields}
			<div class="epesi-rv-row">
				<div class="label">{$f.label}{if $f.required}*{/if}{$f.advanced}</div>
				<div class="data {$f.style}" id="_{$f.element}__data">
					{if $f.error}{$f.error}{/if}
					{if $f.help}
						<div class="help"><img src="{$f.help.icon}" alt="help" {$f.help.text}></div>
					{/if}
					<div>
						{$f.html}{if $action == 'view'}&nbsp;{/if}
					</div>
				</div>
			</div>
		{/foreach}
	</div>
{/if}
{* Long text fields were always full-width, single-column - unaffected by
   the column split either before or after this change, so left going
   through single_field.tpl/{$f.full_field} in a plain table exactly as
   before, just without the outer colspan wrapper (no longer needed, since
   there's no outer table to span). *}
<table cellpadding="0" cellspacing="0" border="0" class="longfields {if $action == 'view'}view{else}edit{/if}" style="border-top: none;">
	{foreach key=k item=f from=$longfields name=fields}
		{$f.full_field}
	{/foreach}
</table>
</div>

{if $main_page}
{php}
	if (isset($this->_tpl_vars['focus'])) eval_js('focus_by_id(\''.$this->_tpl_vars['focus'].'\');');
{/php}
{/if}

</div>

 		</div>
	</div>
