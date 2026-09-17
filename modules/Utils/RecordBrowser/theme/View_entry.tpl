{* $fields (short fields only) and $secondary_fields (multiselect + long
   text, in their shared RecordBrowser field order - RecordBrowser_0.php's
   view_entry_details()) are both PHP-built now, no template-side splitting.
   No row/column pre-computation either: the fluid CSS multi-column container
   below (.epesi-rv-fluid) lets the browser decide how many columns fit,
   based on available width, instead of a fixed PHP-computed count. A long
   text row inside that same container gets column-span:all (View_entry.css)
   so it still renders full-width and breaks the column flow, while keeping
   its position relative to any multiselect fields around it. *}

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

{* Field grid - was a <table> of <table>s with a PHP-computed fixed column
   count (RecordBrowser_0.php::view_entry_details()'s old $cols param); now a
   flat sequence of divs inside a CSS multi-column container so the browser
   picks however many columns fit the available width. Built directly from
   each field's raw pieces ($f.label/.html/.error/.help/etc, all provided by
   get_field_display_options() alongside the pre-rendered $f.full_field) -
   NOT through single_field.tpl (which now also emits divs, not <tr>/<td>,
   but this generic template still builds its rows inline rather than going
   through it, since it needs the CSS multi-column wrapper single_field.tpl
   doesn't know about). Contact.tpl/mails.tpl/PhoneCall's and Meeting's
   default.tpl/Attachment's View_entry.tpl also went fully div-based, using
   the fixed-column-count .epesi-rv-columns/.column pattern instead of this
   file's fluid CSS-multicolumn one. Reuses the existing .label/.data/
   .form_error/.automulti/etc classes and CSS directly - all already
   unscoped from any table ancestor requirement - except a handful of
   view/edit-mode-specific rules that *were* table.view/table.edit-scoped,
   which have an equivalent added under .epesi-rv-fluid.view/.edit in
   View_entry.css instead. *}
<div class="Utils_RecordBrowser__View_entry">
<div class="epesi-rv-fluid {if $action == 'view'}view{else}edit{/if}">
	{foreach key=k item=f from=$fields name=fields}
		{if !isset($focus) && $f.type=="text"}
			{assign var=focus value=$f.element}
		{/if}
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
{if !empty($secondary_fields)}
	{* Multiselect and long text fields, in their shared RecordBrowser field
	   order. Still one fluid multi-column container, so multiselect rows
	   flow side by side same as before - a long text row instead gets
	   column-span:all (View_entry.css) to force a full-width break at its
	   exact position in that order, rather than always sinking below every
	   multiselect. *}
	<div class="epesi-rv-fluid multiselects {if $action == 'view'}view{else}edit{/if}">
		{foreach key=k item=f from=$secondary_fields name=fields}
			<div class="epesi-rv-row{if $f.type == 'long text' || $f.type == 'file'} long_row{/if}">
				<div class="label{if $f.type == 'long text' || $f.type == 'file'} long_label{/if}">{$f.label}{if $f.required}*{/if}{$f.advanced}</div>
				<div class="data{if $f.type == 'long text' || $f.type == 'file'} long_data{/if} {$f.style}" id="_{$f.element}__data">
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
</div>

{if $main_page}
{php}
	if (isset($this->_tpl_vars['focus'])) eval_js('focus_by_id(\''.$this->_tpl_vars['focus'].'\');');
{/php}
{/if}

</div>

 		</div>
	</div>
