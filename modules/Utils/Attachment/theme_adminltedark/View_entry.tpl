{* Utils_Attachment registers this as the per-table template for the
   'utils_attachment' record type (AttachmentInstall.php calls
   Utils_RecordBrowserCommon::set_tpl('utils_attachment', ...)) - same
   bypass-of-generic-View_entry.tpl mechanism as CRM_Contacts's Contact.tpl
   (see ../../../CRM/Contacts/theme_adminlte/Contact.tpl and
   [[adminlte-theme-incomplete]] memory).

   Layout (2026-07-29): Title stands alone in its own big/bold row (no
   "Title:" label - it reads as a heading, not a field), then Edited
   by/Edited on/Permission/Sticky share one row, then Encryption (the
   'crypted' field) gets a full-width row of its own - its edit-mode
   QuickForm group (checkbox + Password + Confirm Password + Password Hint)
   needs the whole row's width to lay out in one line; cramped into a
   regular 1/3 or 1/4 column it wraps and the row balloons to match its
   height, dragging Permission/Sticky's cells tall along with it since they
   share the same row. Then Attached to, then the note body. Structure
   mirrors View_entry.tpl/Contact.tpl's own conversion to flex
   (.epesi-rv-columns/.column/.view/.edit/.epesi-rv-row/.label/.data
   instead of a <table> of <table>s) - real per-field content, not
   decoration, unchanged beyond the wrapper markup.
   'Edited by' and 'Edited on' split the single 'edited_on' field's combined
   display_date() text (see AttachmentCommon_0.php's $last_editor_info) into
   two columns - only available in 'view' mode, since 'add'/'edit' render
   that field via QFfield_date (a frozen static, not display_date())
   instead; those modes fall back to a single 'Edited on' cell.
   View_entry.css (loaded alongside any custom $tpl by RecordBrowser_0.php)
   already covers .label/.data/.column/etc, so no separate CSS needed here. *}
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
    $this->assign('edited_by_caption', __('Edited by'));
    $this->assign('editor_label', Utils_AttachmentCommon::display_editor_label());
    $this->assign('edited_date_label', Utils_AttachmentCommon::display_edited_date_label());
{/php}

{literal}
<style>
.epesi-attachment-title { font-size: 1.4em; font-weight: 600; padding: 10px 14px; }
.epesi-attachment-title input[type=text] { font-size: 1em; font-weight: 600; width: 100%; }
/* QFfield_crypted's Password/Confirm Password/Password Hint inputs (see
   AttachmentCommon_0.php) start disabled and are enabled by the Encryption
   checkbox's onChange JS, which only ever toggles the disabled property -
   whatever makes them render white-on-white once enabled, force readable
   text explicitly rather than chase it through inherited/global rules. */
#note_password, #note_password2, #note_password_hint {
	color: #212529 !important;
	background-color: #fff !important;
}
</style>
{/literal}

{if $main_page}
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

{/if}

<div class="epesi-rv-card{if $main_page} card{/if}">
	<div class="card-body p-0">

		<div class="Utils_RecordBrowser__container">
		<div class="Utils_RecordBrowser__View_entry">

			{* Row 1: Title alone, styled as a heading *}
			<div class="epesi-attachment-title">
				{$fields.title.html}
			</div>

			{if $action == 'view'}
			{* Row 2 (view): Edited by / Edited on / Permission / Sticky *}
			<div class="epesi-rv-columns">
					<div class="column">
						<div class="view">
						<div class="epesi-rv-row">
							<div class="label">{$edited_by_caption}</div>
							<div class="data">{$editor_label}</div>
						</div>
						</div>
					</div>
					<div class="column">
						<div class="view">
						<div class="epesi-rv-row">
							<div class="label">{$fields.edited_on.label}</div>
							<div class="data">{$edited_date_label}</div>
						</div>
						</div>
					</div>
					<div class="column">
						<div class="view">
							{$fields.permission.full_field}
						</div>
					</div>
					<div class="column">
						<div class="view">
							{$fields.sticky.full_field}
						</div>
					</div>
			</div>

			{* Row 3 (view): Encryption, full width so its edit-mode group fits on one line *}
			<div class="epesi-rv-columns">
					<div class="column" style="flex: 1 1 0; min-width: 0;">
						<div class="view">
							{$fields.crypted.full_field}
						</div>
					</div>
			</div>
			{else}
			{* Row 2 (add/edit): no 'Edited on' - it's always frozen/system-managed,
			   not something to edit here. Permission / Sticky / Encryption share
			   one row instead - natural (non-percentage) column widths let
			   Encryption's group take however much of the row it needs for its
			   checkbox + Password + Confirm Password + Password Hint fields to
			   stay on one line, while Permission/Sticky stay their normal size. *}
			<div class="epesi-rv-columns">
					<div class="column">
						<div class="edit">
							{$fields.permission.full_field}
						</div>
					</div>
					<div class="column">
						<div class="edit">
							{$fields.sticky.full_field}
						</div>
					</div>
					<div class="column" style="flex: 1 1 0; min-width: 0;">
						<div class="edit">
							{$fields.crypted.full_field}
						</div>
					</div>
			</div>
			{/if}

			{* Any other generic fields this recordset gains in future (none currently), then Row 4: Attached to *}
			<div class="epesi-rv-columns">
					{assign var=x value=1}
					{assign var=y value=1}
					{foreach key=k item=f from=$fields name=fields}
						{if $k!='title' && $k!='permission' && $k!='edited_on' && $k!='sticky' && $k!='crypted'}
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

			{* Row 5: body of the note (and any other longfields, none currently) *}
			<div class="longfields {if $action == 'view'}view{else}edit{/if}">
					<div class="epesi-rv-row">
					<div class="data long_data {$longfields.note.style}" id="_{$longfields.note.element}__data">
						{if $longfields.note.error}{$longfields.note.error}{/if}
						{if $longfields.note.help}
							<div class="help"><img src="{$longfields.note.help.icon}" alt="help" {$longfields.note.help.text}></div>
						{/if}
						<div>
							{$longfields.note.html}{if $action == 'view'}&nbsp;{/if}
						</div>
					</div>
					</div>
			</div>
			<div class="longfields {if $action == 'view'}view{else}edit{/if}">
					{foreach key=k item=f from=$longfields name=fields}
						{if $k!='note'}
							{$f.full_field}
						{/if}
					{/foreach}
			</div>

			{if $main_page}
				{php}
					if (isset($this->_tpl_vars['focus'])) eval_js('focus_by_id(\''.$this->_tpl_vars['focus'].'\');');
				{/php}
			{/if}

		</div>
		</div>

	</div>
</div>
