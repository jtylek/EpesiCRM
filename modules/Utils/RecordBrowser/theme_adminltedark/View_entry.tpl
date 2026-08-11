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

{* Only the record header/container chrome is restyled here (a Bootstrap
   card instead of the gray rounded box). The field grid below now uses a
   fluid CSS multi-column layout, same approach as the default theme's own
   copy of this file (see its comments for the full rationale) - the
   per-field markup itself ($f.full_field) is built per field-type elsewhere
   and shared with the default theme, so View_entry.css re-skins it by class
   name instead (.label/.data/.form_error/.automulti/.timestamp/etc - all
   already scoped under .Utils_RecordBrowser__View_entry, so no collision
   risk with other screens). *}
{if $main_page}
<div class="epesi-rv-header">
	{* Per request: module icon + caption ("Contacts") dropped from this
	   header, same as Browsing_records.tpl - $icon/$caption are still
	   assigned by RecordBrowser_0.php (shared with the default theme), just
	   not rendered here. The required-note/tooltips row is unrelated chrome
	   and stays. Required-note split into its own left-aligned element, per
	   request - it was sharing .epesi-rv-tools' right-aligned flex row with
	   the icon tooltips, so it always ended up glued to the icons instead of
	   acting as an independent page note. *}
	{if $required_note}
	<div class="epesi-rv-required">
		*&nbsp;{$required_note}
	</div>
	{/if}
	<div class="epesi-rv-tools">
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

{/if}

{* Bootstrap's .card (white background/border/shadow) is only appropriate
   when this is the standalone record-view page ($main_page) - the same
   View_entry.tpl also renders as one tab's content inside
   Utils_TabbedBrowser (e.g. a "Details" tab alongside Notes/E-mails/...),
   where the surrounding tab body already sits on the page's own grey
   background; keeping the card there left a white box with its own border
   floating inside that grey area instead of blending into it. *}
<div class="epesi-rv-card{if $main_page} card{/if}">
	<div class="card-body p-0">

<div class="Utils_RecordBrowser__container">

{* Field grid - was a <table> of <table>s, then a PHP-computed fixed number
   of flex .column divs; now a flat sequence of .epesi-rv-row divs inside a
   CSS multi-column container (.epesi-rv-fluid, see View_entry.css) - the
   browser decides how many columns fit at the current width, same idea as
   text reflowing in a newspaper layout. .epesi-rv-row/.label/.data are
   unscoped from any particular container (already true - both this generic
   template and per-table overrides like Contact.tpl share them), so no new
   field-level CSS was needed, only a new container rule. *}
<div class="Utils_RecordBrowser__View_entry">
<div class="epesi-rv-fluid {if $action == 'view'}view{else}edit{/if}">
	{foreach key=k item=f from=$fields name=fields}
		{if $f.type!="multiselect"}
			{if !isset($focus) && $f.type=="text"}
				{assign var=focus value=$f.element}
			{/if}
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

{if $main_page}
{php}
	if (isset($this->_tpl_vars['focus'])) eval_js('focus_by_id(\''.$this->_tpl_vars['focus'].'\');');
{/php}
{/if}

</div>

	</div>
</div>
