{$form_open}

<table id="CRM_Contacts_Activities">
	<tr>
		<td align="left" width="50%">
			<table>
				<tr>
					<td class="label">
						{$form_data.header.display}
					</td>
					<td class="label">
						{$form_data.events.label}
					</td>
					<td class="data">
						{$form_data.events.html}
					</td>
					<td class="label">
						{$form_data.tasks.label}
					</td>
					<td class="data">
						{$form_data.tasks.html}
					</td>
					<td class="label">
						{$form_data.phonecalls.label}
					</td>
					<td class="data">
						{$form_data.phonecalls.html}
					</td>
					<td class="label">
						{$form_data.closed.label}
					</td>
					<td class="data">
						{$form_data.closed.html}
					</td>
				</tr>
			</table>
		</td>
		<td class="actions">
			{if isset($__link.new_event.open)}
				&nbsp;&nbsp;&nbsp;{$new_event}
			{/if}
			{if isset($__link.new_task.open)}
				&nbsp;&nbsp;&nbsp;{$new_task}
			{/if}
			{if isset($__link.new_phonecall.open)}
				&nbsp;&nbsp;&nbsp;{$new_phonecall}
			{/if}
		</td>
	</tr>
</table>
		

{$form_close}
