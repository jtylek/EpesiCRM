{$form_open}
<div style="white-space: normal;">
<div style="float:right;">
<table class="Utils_Attachment__browse_panel">
	<tr>
		<td class="epesi_label">
			{$form_data.filter_text.label}
		</td>
		<td class="epesi_data" style="min-width:80px;">
			{$form_data.filter_text.html}
		</td>
	{if isset($form_data.filter_user.html)}
		<td class="epesi_label">
			{$form_data.filter_user.label}
		</td>
		<td class="epesi_data" style="min-width:80px;">
			{$form_data.filter_user.html}
		</td>
	{/if}
		<td class="epesi_label">
			{$form_data.filter_start.label}
		</td>
		<td class="epesi_data">
			{$form_data.filter_start.html}
		</td>
		<td class="epesi_label">
			{$form_data.filter_end.label}
		</td>
		<td class="epesi_data">
			{$form_data.filter_end.html}
		</td>
	{if isset($show_deleted)}
		<td class="epesi_label">
			{$show_deleted.label}
		</td>
		<td class="epesi_data" style="width:25px;nowrap;">
			<input type="checkbox" {$show_deleted.default} onChange="if(this.checked){$show_deleted.show} else {$show_deleted.hide}" />
		</td>
	{/if}
		<td class="child_button">
			{$form_data.submit_button.html}
		</td>
	</tr>
</table>
</div>
<div style="float:left;">
<table class="Utils_Attachment__browse_panel" style="width:1px;">
	<tr>
		{if isset($new_note)}
		<td style="width:110px;">
			<a class="attachment_add_new" {$new_note.href}><img src="{$theme_dir}/Base/ActionBar/icons/add-small.png" />
				<div class="attachment_div_add_new">
					{$new_note.label}
				</div>
			</a>
		</td>
		{/if}
		{if isset($multiple_attachments)}
		<td style="width:110px;">
			{$multiple_attachments}
		</td>
		{/if}
		<td style="width:110px;" id="{$expand_collapse.e_id}">
			<a class="attachment_add_new" {$expand_collapse.e_href}><img src="{$theme_dir}/Base/ActionBar/icons/expand_big.png" />
				<div class="attachment_div_add_new">
					{$expand_collapse.e_label}
				</div>
			</a>
		</td>
		<td style="width:110px;display:none;" id="{$expand_collapse.c_id}">
			<a class="attachment_add_new" {$expand_collapse.c_href}><img src="{$theme_dir}/Base/ActionBar/icons/collapse_big.png" />
				<div class="attachment_div_add_new">
					{$expand_collapse.c_label}
				</div>
			</a>
		</td>
	{if isset($paste)}
		<td style="width:110px;">
			<a class="attachment_add_new" {$paste.href}>
				<div class="attachment_div_add_new">
					{$paste.label}
				</div>
			</a>
		</td>
	{/if}
	</tr>
</table>
</div>
</div>
{$form_close}		
