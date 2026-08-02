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
	$this->_tpl_vars['cols'] = (int)$this->_tpl_vars['cols']; if ($this->_tpl_vars['cols'] < 1) $this->_tpl_vars['cols'] = 1; // PHP 8: cols may arrive as string/empty; cast to int + guard against div-by-zero
	$this->_tpl_vars['rows'] = ceil($this->_tpl_vars['count']/$this->_tpl_vars['cols']);
	$this->_tpl_vars['mss_rows'] = ceil(count($this->_tpl_vars['multiselects'])/$this->_tpl_vars['cols']);
	$this->_tpl_vars['no_empty'] = $this->_tpl_vars['count']-floor($this->_tpl_vars['count']/$this->_tpl_vars['cols'])*$this->_tpl_vars['cols'];
	if ($this->_tpl_vars['no_empty']==0) $this->_tpl_vars['no_empty'] = $this->_tpl_vars['cols']+1;
	$this->_tpl_vars['mss_no_empty'] = count($this->_tpl_vars['multiselects'])-floor(count($this->_tpl_vars['multiselects'])/$this->_tpl_vars['cols'])*$this->_tpl_vars['cols'];
	if ($this->_tpl_vars['mss_no_empty']==0) $this->_tpl_vars['mss_no_empty'] = $this->_tpl_vars['cols']+1;
	$this->_tpl_vars['cols_percent'] = 100 / $this->_tpl_vars['cols'];
{/php}

{* Only the record header/container chrome is restyled here (a Bootstrap
   card instead of the gray rounded box) - the field grid below is left
   byte-for-byte identical to the default theme's column-balancing table
   logic (real layout math, not decoration) and the field markup itself
   ($f.full_field) is built per field-type elsewhere and shared with the
   default theme, so View_entry.css re-skins it by class name instead
   (.label/.data/.form_error/.automulti/.timestamp/etc - all already scoped
   under .Utils_RecordBrowser__View_entry, so no collision risk with other
   screens). *}
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
	<div class="epesi-rv-required">
		*&nbsp;{$required_note}
	</div>
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

{* Field grid - was a <table> of <table>s (one outer row, N td.column cells,
   each holding its own column-balanced inner table); now the equivalent
   nesting in flex/grid-friendly divs, so it reflows on narrow screens
   without the display:block responsive-table hack the table version needed.
   The row/column *distribution* math (rows/no_empty/cols_percent, computed
   above) is unchanged - only the markup each transition point emits. The
   old invisible "filler" row that padded a shorter column up to its taller
   sibling's row count (purely so two side-by-side <table>s looked
   even) is dropped: flex columns don't need equal row counts to look
   right. *}
<div class="Utils_RecordBrowser__View_entry">
<div class="epesi-rv-columns">
	{assign var=x value=1}
	{assign var=y value=1}
	{foreach key=k item=f from=$fields name=fields}
		{if $f.type!="multiselect"}
			{if !isset($focus) && $f.type=="text"}
				{assign var=focus value=$f.element}
			{/if}

			{if $y==1}
			<div class="column" style="width: {$cols_percent}%;">
				<div class="{if $action == 'view'}view{else}edit{/if}">
			{/if}
					{$f.full_field}
			{if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
				{assign var=y value=1}
				{assign var=x value=$x+1}
				</div>
			</div>
			{else}
				{assign var=y value=$y+1}
			{/if}
		{/if}
	{/foreach}
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

{if $main_page}
{php}
	if (isset($this->_tpl_vars['focus'])) eval_js('focus_by_id(\''.$this->_tpl_vars['focus'].'\');');
{/php}
{/if}

</div>

	</div>
</div>
