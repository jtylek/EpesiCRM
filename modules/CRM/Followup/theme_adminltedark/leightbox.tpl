{* CRM_Followup's "Follow-up" popup (CRM_FollowupCommon::drawLeightbox(),
   e.g. shown after closing a Phonecall) - only $form_open/$form_closecancel/
   $form_note/$form_close and $just_close/$new_meeting/$new_task/
   $new_phonecall's .open/.text/.close pieces are fixed by PHP (the <a
   onclick=...> wrapper and its label text); everything else here is this
   theme's own markup, not shared with the default theme's leightbox.tpl -
   safe to rebuild rather than reskin. Fresh .epesi-followup-* classes
   rather than reusing .epesi_big_button/.epesi_label/.epesi_data (those are
   the default theme's own reused-elsewhere classes - see
   [[adminlte-css-conflicts]] on why restyling shared class names under an
   id is a trap; several already-themed screens this session, e.g.
   Base_Admin/Base_User_Settings, made the same choice for the same reason). *}
{$form_open}
<div class="epesi-followup-fields">
	<div class="epesi-followup-row">
		<label class="epesi-followup-label">{$form_closecancel.label}</label>
		<div class="epesi-followup-control">{$form_closecancel.html}</div>
	</div>
	<div class="epesi-followup-row">
		<label class="epesi-followup-label">{$form_note.label}</label>
		<div class="epesi-followup-control">{$form_note.html}</div>
	</div>
</div>

<div class="epesi-followup-actions">
	{$just_close.open}
		<span class="epesi-followup-btn epesi-followup-btn-primary">
			<i class="bi bi-check2-square"></i>
			<span class="epesi-followup-btn-label">{$just_close.text}</span>
		</span>
	{$just_close.close}

	{if isset($new_meeting) || isset($new_task) || isset($new_phonecall)}
		<span class="epesi-followup-sep">{"Or save and create:"|t}</span>

		{if isset($new_meeting)}
		{$new_meeting.open}
			<span class="epesi-followup-btn">
				<i class="bi bi-calendar-event"></i>
				<span class="epesi-followup-btn-label">{$new_meeting.text}</span>
			</span>
		{$new_meeting.close}
		{/if}

		{if isset($new_task)}
		{$new_task.open}
			<span class="epesi-followup-btn">
				<i class="bi bi-list-task"></i>
				<span class="epesi-followup-btn-label">{$new_task.text}</span>
			</span>
		{$new_task.close}
		{/if}

		{if isset($new_phonecall)}
		{$new_phonecall.open}
			<span class="epesi-followup-btn">
				<i class="bi bi-telephone-fill"></i>
				<span class="epesi-followup-btn-label">{$new_phonecall.text}</span>
			</span>
		{$new_phonecall.close}
		{/if}
	{/if}
</div>
{$form_close}
