<table width="100%">
	<tr>
		<td style="width:1px;">
			<a class="attachment_add_new" {$new_note.href}><img border="0" src="{$theme_dir}/Base/ActionBar/icons/add-small.png" />
				<div class="attachment_div_add_new">
					{$new_note.label}
				</div>
			</a>
		</td>
		<td style="width:1px;" id="{$expand_collapse.e_id}">
			<a class="attachment_add_new" {$expand_collapse.e_href}><img border="0" src="{$theme_dir}/Base/ActionBar/icons/add-small.png" />
				<div class="attachment_div_add_new">
					{$expand_collapse.e_label}
				</div>
			</a>
		</td>
		<td style="width:1px;display:none;" id="{$expand_collapse.c_id}">
			<a class="attachment_add_new" {$expand_collapse.c_href}><img border="0" src="{$theme_dir}/Base/ActionBar/icons/add-small.png" />
				<div class="attachment_div_add_new">
					{$expand_collapse.c_label}
				</div>
			</a>
		</td>
	{if isset($paste)}
		<td style="width:1px;">
			<a class="attachment_add_new" {$paste.href}>
				<div class="attachment_div_add_new">
					{$paste.label}
				</div>
			</a>
		</td>
	{/if}

		<td>
		</td>
	{if isset($show_deleted)}
		<td style="width:1px;" nowrap="1">
			<input type="checkbox" {$show_deleted.default} onChange="if(this.checked){$show_deleted.show} else {$show_deleted.hide}" />
		</td>
		<td style="width:95px;background:#336699;padding:5px;" nowrap="1">
			{$show_deleted.label}
		</td>
	{/if}
	</tr>
</table>
