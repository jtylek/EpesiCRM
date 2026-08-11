{* Utils_Attachment registers this as the per-table template for the
   'utils_attachment' record type (AttachmentInstall.php calls
   Utils_RecordBrowserCommon::set_tpl('utils_attachment', ...)) - same
   bypass-of-generic-View_entry.tpl mechanism as CRM_Contacts's Contact.tpl
   (see ../../../CRM/Contacts/theme_adminlte/Contact.tpl and
   [[adminlte-theme-incomplete]] memory).

   Layout (2026-08-05 redesign, per request; reordered same day - Edited
   by/Edited on moved after the note body, per follow-up request): Title
   (label + input, one line), then the note body (CKEditor in add/edit,
   rendered text in view) full width, then Edited by/Edited on (view mode
   only, each column an equal flex share so the pair spans the row's full
   width), then Files full width, then a two-column row (Attached to on
   the left; Permission and Sticky stacked on the right), then Encryption
   full width - its
   edit-mode QuickForm group renders the on/off toggle (see 'epesi-switch',
   shared with Libs/QuickForm's array-declared settings checkboxes) alone
   on the first line, with the Password/Confirm Password/Password Hint
   trio in a wrapper div (#note_crypted_details, built in
   AttachmentCommon_0.php's QFfield_crypted via 'static' open/close-tag
   elements) that JS shows/hides based on the toggle instead of just
   disabling its inputs. A trailing generic-fields/multiselects fallback
   loop (excluding every field placed explicitly above) keeps the template
   forward-compatible if this recordset ever gains new fields, same intent
   as the original version of this file.
   Structure mirrors View_entry.tpl/Contact.tpl's own conversion to flex
   (.epesi-rv-columns/.column/.view/.edit/.epesi-rv-row/.label/.data
   instead of a <table> of <table>s) - real per-field content, not
   decoration, unchanged beyond the wrapper markup.
   'Edited by' and 'Edited on' split the single 'edited_on' field's combined
   display_date() text (see AttachmentCommon_0.php's $last_editor_info) into
   two columns - only available in 'view' mode, since 'add'/'edit' render
   that field via QFfield_date (a frozen static, not display_date())
   instead; those modes fall back to a single 'Edited on' cell (not shown
   at all here - it's always frozen/system-managed, not something to edit).
   View_entry.css (loaded alongside any custom $tpl by RecordBrowser_0.php)
   already covers .label/.data/.column/etc, so no separate CSS needed here
   for the grid itself. *}
{* Get total number of fields to display *}
{assign var=count value=0}
{php}
    $this->_tpl_vars['multiselects'] = array();
{/php}
{foreach key=k item=f from=$fields name=fields}
    {if $f.type!="multiselect"}
        {assign var=count value=$count+1}
    {elseif $k!='attached_to'}
        {* 'attached_to' is placed explicitly (Attached to/Permission/Sticky
           row below), not via the generic fallback multiselects loop - any
           other future multiselect field still falls through to it. *}
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
.epesi-attachment-title-row .label { font-weight: 600; }
.epesi-attachment-title-row input[type=text] { font-size: 1.1em; font-weight: 600; }
/* The Files field (Utils_FileUpload_Dropzone::add_to_form()) is always added
   as a frozen 'static' element - even in add/edit mode, since the dropzone
   widget manages its own upload via JS rather than a native form input - so
   it always carries RecordBrowser's '.frozen' style class. That collides
   with View_entry.css's ".edit .frozen{height:32px}" rule (written for
   genuinely single-line frozen fields), forcing this row's whole .data cell
   to 32px while the dropzone itself renders much taller, so it visually
   bled over the row below instead of pushing it down. #_files__data's id
   specificity overrides that rule for this one field without touching the
   shared rule (still correct for actual single-line frozen fields). */
#_files__data {
	height: auto;
}
/* The 'attached_to' multiselect's automulti widget (Libs/QuickForm/
   FieldTypes/automulti/automulti.php) renders its search box/zoom-icon/
   Remove-button row and its selected-items <select> as a plain <table>
   with no width set - View_entry.css's shared ".automulti" rules (used by
   every RecordBrowser table, not just this one) don't set it either, so it
   only shrinks-to-fit its content instead of filling the now much wider
   left column this redesign gives it, leaving bare space to the right.
   Scoped to this one field via #_attached_to__data instead of widening the
   shared rule, which other modules' automulti fields may be sized around. */
#_attached_to__data table.automulti {
	width: 100%;
}
/* .epesi-switch's own sizing/track/knob used to lose to View_entry.css's
   higher-specificity ".data input:not([type=button])"/"[type=checkbox]"
   rules here - this is now fixed generically, for every RecordBrowser
   'epesi-switch' checkbox (Crypted/Sticky here, CRM_Meeting's Timeless,
   etc), in Utils/RecordBrowser/theme_adminltedark/View_entry.css itself
   instead of duplicated per-field via #crypted/#sticky id selectors. */
/* View mode's frozen Sticky/Crypted checkboxes now get their switch-glyph
   look from the app-wide fix in Libs/QuickForm/theme_adminltedark/
   default.css (.epesi-frozen-checkbox, HTML_QuickForm_epesi_checkbox/
   epesi_advcheckbox's getFrozenHtml()) - no per-field override needed here
   anymore. */
/* QFfield_crypted's Password/Confirm Password/Password Hint inputs (see
   AttachmentCommon_0.php) start disabled and are enabled/shown by the
   Encryption toggle's onChange JS - whatever makes them render white-on-
   white once enabled, force readable text explicitly rather than chase it
   through inherited/global rules. A visible border is added for the same
   reason as the switch above: the shared ".data input" rule's border:0px
   left these with no boundary at all, so once their forced white
   background landed on light mode's equally-white .data cell, the fields
   themselves became invisible - only their labels showed. */
#note_password, #note_password2, #note_password_hint {
	color: #212529 !important;
	background-color: #fff !important;
	border: 1px solid #adb5bd !important;
}
/* Wraps the Password/Confirm Password/Password Hint trio (see
   AttachmentCommon_0.php's QFfield_crypted) so they lay out on one line on
   wide screens and can be hidden as a block by the Encryption toggle's
   onChange JS, instead of just disabling the inputs in place. */
.epesi-attachment-crypted-details {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-start;
	gap: 8px 12px;
	margin-top: 8px;
}
/* Each Password/Confirm Password/Password Hint field is its own flex item
   (label stacked above its input, per request) instead of the label and
   input being flat siblings of the row above - as flat siblings, the wrap
   point could land between a label and its own input on narrow screens,
   stranding the label at the end of one line and the input alone at the
   start of the next. Grouping label+input into one flex item keeps them
   together regardless of where the row wraps. flex:1 1 0 (equal, shared
   growth) instead of a fixed input width - now that the addGroup()
   separator fix (AttachmentCommon_0.php) stopped the row from wrapping
   early, three fixed ~110px-wide fields left the rest of this full-width
   row's space empty; growing all three evenly instead spans the whole
   column, per request. min-width is the wrap floor on narrow screens. */
.epesi-attachment-crypted-field {
	display: flex;
	flex-direction: column;
	flex: 1 1 0;
	min-width: 140px;
}
.epesi-attachment-crypted-label {
	white-space: nowrap;
	margin-bottom: 2px;
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

			{* Row 1: Title - label and input on one line *}
			<div class="epesi-attachment-title-row">
				{$fields.title.full_field}
			</div>

			{* Row 2: body of the note - CKEditor (add/edit) or rendered text
			   (view), full width. (Any other longfields this recordset gains
			   in future, none currently.) *}
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

			{if $action == 'view'}
			{* Row 3 (view only): Edited by / Edited on - each column is an
			   equal flex-grow share (matching the Files/Attached-to-etc rows
			   below) instead of the bare '.column' default (flex:0 0 auto,
			   no explicit width - shrinks to fit its label+value content),
			   so the pair together spans the row's full width instead of
			   leaving the rest of the row blank, per request. *}
			<div class="epesi-rv-columns">
					<div class="column" style="flex: 1 1 0; min-width: 0;">
						<div class="view">
						<div class="epesi-rv-row">
							<div class="label">{$edited_by_caption}</div>
							<div class="data">{$editor_label}</div>
						</div>
						</div>
					</div>
					<div class="column" style="flex: 1 1 0; min-width: 0;">
						<div class="view">
						<div class="epesi-rv-row">
							<div class="label">{$fields.edited_on.label}</div>
							<div class="data">{$edited_date_label}</div>
						</div>
						</div>
					</div>
			</div>
			{/if}

			{* Row 4: Files, full width *}
			{if isset($fields.files)}
			<div class="epesi-rv-columns">
					<div class="column" style="flex: 1 1 0; min-width: 0;">
						<div class="{if $action == 'view'}view{else}edit{/if}">
							{$fields.files.full_field}
						</div>
					</div>
			</div>
			{/if}

			{* Row 5: Attached to (left column), Permission + Sticky stacked
			   (right column) *}
			<div class="epesi-rv-columns">
					<div class="column" style="flex: 1 1 0; min-width: 0;">
						<div class="{if $action == 'view'}view{else}edit{/if}">
							{$fields.attached_to.full_field}
						</div>
					</div>
					<div class="column" style="flex: 1 1 0; min-width: 0;">
						<div class="{if $action == 'view'}view{else}edit{/if}">
							{$fields.permission.full_field}
							{$fields.sticky.full_field}
						</div>
					</div>
			</div>

			{* Row 6: Encryption, full width so its edit-mode group (on/off
			   toggle + conditionally-shown Password/Confirm Password/
			   Password Hint trio) fits the whole row's width. *}
			<div class="epesi-rv-columns">
					<div class="column" style="flex: 1 1 0; min-width: 0;">
						<div class="{if $action == 'view'}view{else}edit{/if}">
							{$fields.crypted.full_field}
						</div>
					</div>
			</div>

			{* Any other generic fields this recordset gains in future (none
			   currently) - excludes every field already placed above. *}
			<div class="epesi-rv-columns">
					{assign var=x value=1}
					{assign var=y value=1}
					{foreach key=k item=f from=$fields name=fields}
						{if $k!='title' && $k!='permission' && $k!='edited_on' && $k!='sticky' && $k!='crypted' && $k!='files'}
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

			{if $main_page}
				{php}
					if (isset($this->_tpl_vars['focus'])) eval_js('focus_by_id(\''.$this->_tpl_vars['focus'].'\');');
				{/php}
			{/if}

		</div>
		</div>

	</div>
</div>
