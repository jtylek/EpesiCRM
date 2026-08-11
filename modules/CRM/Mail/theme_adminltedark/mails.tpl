{* CRM_Mail registers this as the per-table template for the 'rc_mails' record type
   (MailInstall.php calls Utils_RecordBrowserCommon::set_tpl('rc_mails', ...)) - same bypass-of-
   View_entry.tpl mechanism as CRM_Contacts's Contact.tpl / CRM_PhoneCall's default.tpl (see
   ../../Contacts/theme_adminlte/Contact.tpl and [[adminlte-theme-incomplete]] memory). Only ever
   selected when $main_page is true (see RecordBrowser_0.php::view_entry()), so no {if $main_page}
   guard is needed structurally - kept anyway around the header/tooltips row, matching this file's
   own prior version.

   Previous version of this file was a stray copy of Utils/Attachment's View_entry.tpl (referenced
   $fields.title/.sticky/.crypted/.permission/$longfields.note - none of which exist on rc_mails,
   hence the "Undefined array key" warnings) rather than anything built for Mail's own fields.
   Rebuilt from scratch to a fixed layout (per request): Subject full width; From+Date; To+Cc;
   Employee+Date archived+Thread; Contacts+Related; Body full width; Attachments underneath as
   plain download links (no inline image preview even for PNG/JPG - an e-mail's own inline
   images already render within the Body iframe itself, see get_html.php) - deliberately NOT the
   auto-flowing .epesi-rv-fluid masonry the generic View_entry.tpl/PhoneCall's default.tpl use
   elsewhere, since that lets the browser decide which fields land in the same row/column instead
   of this fixed pairing.

   Two of the six rows use data that isn't a plain rc_mails field:
    - Cc: only exists inside the raw archived headers blob (headers_data - blocked from the
      normal 'view' field ACL, see MailInstall.php), so there's no $fields.cc to render. Body/
      Attachments/Headers used to be separate addon tabs below the main record (mail_body_addon/
      attachments_addon/mail_headers_addon) - now unregistered (MailInstall.php + a patch for
      existing installs) since Body and Attachments render inline here instead, and the raw
      Headers dump isn't needed now that Cc is parsed out of it directly (see
      CRM_MailCommon::get_cc_html()).
    - Date archived: when the record was archived into EPESI, i.e. created_on record metadata
      (Utils_RecordBrowserCommon::get_record_info()), not itself an rc_mails column - distinct
      from the 'date' field (the e-mail's own Date header, shown next to From above).
   Both are computed in the {php} block below and rendered with the same .epesi-rv-row/.label/
   .data markup single_field.tpl uses for real fields, for a consistent look.

   View_entry.css (loaded alongside any custom $tpl by RecordBrowser_0.php) covers .label/.data/
   .epesi-rv-columns/.column/etc; mails.css (this theme) only adds the attachment list styling,
   replacing the legacy (non-adminlte) theme/mails.css's ".direction" rule, which doesn't apply to
   this flex-based markup. *}
{php}
	$mail_id = $this->_tpl_vars['raw_data']['id'];
	$this->assign('mail_from_html', CRM_MailCommon::format_address_list($this->_tpl_vars['raw_data']['from']));
	$this->assign('mail_to_html', CRM_MailCommon::format_address_list($this->_tpl_vars['raw_data']['to']));
	$this->assign('mail_cc_label', __('Cc'));
	$this->assign('mail_cc_html', CRM_MailCommon::get_cc_html($mail_id));
	$this->assign('mail_archived_label', __('Date archived'));
	$this->assign('mail_archived_html', CRM_MailCommon::get_archived_on_html($mail_id));
	$this->assign('mail_attachments_label', __('Attachments'));
	$this->assign('mail_attachments_html', CRM_MailCommon::get_attachments_html($mail_id));
{/php}

{if $main_page}
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

			{* Subject - full width. Rendered from the raw value rather than
			   $fields.subject.full_field: display_subject() (CRM_MailCommon) also appends a
			   "From: ...<br />To: ..." preview meant for the browse-list row, which would
			   duplicate the dedicated From/To rows below. *}
			<div class="epesi-rv-columns">
				<div class="column" style="width: 100%;">
					<div class="{if $action == 'view'}view{else}edit{/if}">
						<div class="epesi-rv-row">
							<div class="label">{$fields.subject.label}</div>
							<div class="data" id="_subject__data"><div>{$raw_data.subject|escape}&nbsp;</div></div>
						</div>
					</div>
				</div>
			</div>

			{* From | Date. From rendered from the raw value (via CRM_MailCommon::
			   format_address_list()) rather than $fields.from.full_field, same reason as To
			   below - see get_cc_html()'s treatment of Cc for the "why" in full. *}
			<div class="epesi-rv-columns">
				<div class="column" style="width: 50%;">
					<div class="{if $action == 'view'}view{else}edit{/if}">
						<div class="epesi-rv-row">
							<div class="label">{$fields.from.label}</div>
							<div class="data" id="_from__data"><div>{$mail_from_html}&nbsp;</div></div>
						</div>
					</div>
				</div>
				<div class="column" style="width: 50%;">
					<div class="{if $action == 'view'}view{else}edit{/if}">
						{$fields.date.full_field}
					</div>
				</div>
			</div>

			{* To | Cc. To rendered from the raw value (via CRM_MailCommon::
			   format_address_list()) rather than $fields.to.full_field: some archived mail
			   stores "Name email@domain" as one straight-quoted string with no <> around the
			   address at all - reads oddly verbatim, so this reformats it to Name followed by
			   a quoted address instead (per request), same as Cc. *}
			<div class="epesi-rv-columns">
				<div class="column" style="width: 50%;">
					<div class="{if $action == 'view'}view{else}edit{/if}">
						<div class="epesi-rv-row">
							<div class="label">{$fields.to.label}</div>
							<div class="data" id="_to__data"><div>{$mail_to_html}&nbsp;</div></div>
						</div>
					</div>
				</div>
				<div class="column" style="width: 50%;">
					<div class="{if $action == 'view'}view{else}edit{/if}">
						<div class="epesi-rv-row">
							<div class="label">{$mail_cc_label}</div>
							<div class="data" id="_cc__data"><div>{$mail_cc_html}&nbsp;</div></div>
						</div>
					</div>
				</div>
			</div>

			{* Employee | Date archived | Thread *}
			<div class="epesi-rv-columns">
				<div class="column" style="width: calc(100% / 3);">
					<div class="{if $action == 'view'}view{else}edit{/if}">
						{$fields.employee.full_field}
					</div>
				</div>
				<div class="column" style="width: calc(100% / 3);">
					<div class="{if $action == 'view'}view{else}edit{/if}">
						<div class="epesi-rv-row">
							<div class="label">{$mail_archived_label}</div>
							<div class="data" id="_archived_on__data"><div>{$mail_archived_html}&nbsp;</div></div>
						</div>
					</div>
				</div>
				<div class="column" style="width: calc(100% / 3);">
					<div class="{if $action == 'view'}view{else}edit{/if}">
						{$fields.thread.full_field}
					</div>
				</div>
			</div>

			{* Contacts | Related *}
			<div class="epesi-rv-columns">
				<div class="column" style="width: 50%;">
					<div class="{if $action == 'view'}view{else}edit{/if}">
						{$fields.contacts.full_field}
					</div>
				</div>
				<div class="column" style="width: 50%;">
					<div class="{if $action == 'view'}view{else}edit{/if}">
						{$fields.related.full_field}
					</div>
				</div>
			</div>

			{* Any other field (e.g. an admin-added custom field) falls through here instead of
			   silently never rendering - same convention as every other per-table template
			   (Contact.tpl, PhoneCall's default.tpl, etc). *}
			<div class="epesi-rv-columns">
				{foreach key=k item=f from=$fields name=fields}
					{if $k!='subject' && $k!='from' && $k!='date' && $k!='to' && $k!='employee' && $k!='thread' && $k!='contacts' && $k!='related' && $k!='attachments'}
						<div class="column" style="width: 50%;">
							<div class="{if $action == 'view'}view{else}edit{/if}">
								{$f.full_field}
							</div>
						</div>
					{/if}
				{/foreach}
			</div>

			{* Body - full width *}
			<div class="longfields {if $action == 'view'}view{else}edit{/if}">
				{$longfields.body.full_field}
				{foreach key=k item=f from=$longfields name=fields}
					{if $k!='body'}
						{$f.full_field}
					{/if}
				{/foreach}
			</div>

			{* Attachments - full width, underneath Body; always a plain download link, even
			   for PNG/JPG (see MailCommon_0.php::get_attachments_html()). *}
			{if $mail_attachments_html}
			<div class="longfields {if $action == 'view'}view{else}edit{/if}">
				<div class="epesi-rv-row long_row">
					<div class="label long_label">{$mail_attachments_label}</div>
					<div class="data long_data" id="_attachments__data">
						<div class="crm-mail-attachments">{$mail_attachments_html}</div>
					</div>
				</div>
			</div>
			{/if}

		</div>
		</div>

	</div>
</div>
