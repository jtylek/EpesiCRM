{* Utils_Attachment registers this as the per-table template for the
   'utils_attachment' record type (AttachmentInstall.php calls
   Utils_RecordBrowserCommon::set_tpl('utils_attachment', ...)) - same
   bypass-of-generic-View_entry.tpl mechanism as CRM_Contacts's Contact.tpl
   (see ../../../CRM/Contacts/theme_adminlte/Contact.tpl and
   [[adminlte-theme-incomplete]] memory).

   Correction (2026-07-28): the previous version of this file was built from
   the wrong source - it matched CRM_Mail's mails.tpl structure (a single
   generic field loop with an "email"-styled longfield row), not this
   module's own default-theme View_entry.tpl, which gives title/edited_on/
   permission a dedicated 3-column header row, the note a full-width
   longfield row of its own, and sticky/crypted a 2-column row (crypted's
   own <td colspan="2"> giving its multi-part QuickForm group - checkbox +
   Confirm/Password/Password Hint - a full column-and-a-half of width
   instead of being crammed into one regular-width cell). That structural
   loss is what made the "Add new Note" popup's Encryption row look
   cramped/misaligned. Fixed by porting the real structure below - kept
   byte-for-byte identical to the default theme's own layout math, only the
   header/wrapper chrome is adminlte-specific, same as every other template
   in this family. Already has its own {if $main_page} guard around the
   header - kept, mirroring the generic View_entry.tpl's own pattern.
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
{/php}

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

			{* Outside table *}
			<table class="Utils_RecordBrowser__View_entry" cellpadding="0" cellspacing="0" border="0">
				<tbody>
				<tr>
					<td>
						<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
							{$fields.title.full_field}
						</table>
					</td>
					<td>
						<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
							{$fields.edited_on.full_field}
						</table>
					</td>
					<td>
						<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
							{$fields.permission.full_field}
						</table>
					</td>
				</tr>
				<tr>
					<td colspan="3">
						<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
						<tr>
						<td class="data long_data {$longfields.note.style}" id="_{$longfields.note.element}__data">
							{if $longfields.note.error}{$longfields.note.error}{/if}
							{if $longfields.note.help}
								<div class="help"><img src="{$longfields.note.help.icon}" alt="help" {$longfields.note.help.text}></div>
							{/if}
							<div>
								{$longfields.note.html}{if $action == 'view'}&nbsp;{/if}
							</div>
						</td>
						</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td>
						<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
							{$fields.sticky.full_field}
						</table>
					</td>
					<td colspan="2">
						<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
							{$fields.crypted.full_field}
						</table>
					</td>
				</tr>
				</tbody>
			</table>

			<table class="Utils_RecordBrowser__View_entry" cellpadding="0" cellspacing="0" border="0">
				<tbody>
				<tr>
					{assign var=x value=1}
					{assign var=y value=1}
					{foreach key=k item=f from=$fields name=fields}
						{if $k!='title' && $k!='permission' && $k!='edited_on' && $k!='sticky' && $k!='crypted'}
						{if $f.type!="multiselect"}
							{if !isset($focus) && $f.type=="text"}
								{assign var=focus value=$f.element}
							{/if}

							{if $y==1}
								<td class="column" style="width: {$cols_percent}%;">
								<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
							{/if}
							{$f.full_field}
							{if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
								{if $x>$no_empty}
									<tr style="display:none;">
										<td class="label">&nbsp;</td>
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
								<td class="column" style="width: {$cols_percent}%;" colspan="2">
								<table cellpadding="0" cellspacing="0" border="0" class="multiselects {if $action == 'view'}view{else}edit{/if}" style="border-top: none;">
							{/if}
							{$f.full_field}
							{if $y==$mss_rows or ($y==$mss_rows-1 and $x>$mss_no_empty)}
								{if $x>$mss_no_empty}
									<tr style="display:none;">
										<td class="label">&nbsp;</td>
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
					<td colspan="{$cols}">
						<table cellpadding="0" cellspacing="0" border="0" class="longfields {if $action == 'view'}view{else}edit{/if}" style="border-top: none;">
							{foreach key=k item=f from=$longfields name=fields}
								{if $k!='note'}
									{$f.full_field}
								{/if}
							{/foreach}
						</table>
					</td>
				</tr>
				</tbody>
			</table>

			{if $main_page}
				{php}
					if (isset($this->_tpl_vars['focus'])) eval_js('focus_by_id(\''.$this->_tpl_vars['focus'].'\');');
				{/php}
			{/if}

		</div>

	</div>
</div>
