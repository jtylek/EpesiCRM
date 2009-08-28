{$form_open}

<table>
<tr>
<td colspan=2>{$form_data.header.record_header}</td>
</tr>
<tr>
<td colspan=2>
{if isset($record_add_button)}
{$record_add_button}
{/if}
{$form_data.records.error}
{$form_data.records.html}
</td>
</tr>
<tr>
<td colspan=2>{$form_data.header.notification_header}</td>
</tr>
<tr>
<td colspan=2>
{$addressbook_add_button}
{$form_data.to_addr_ex.html}
</td>
</tr>
</table>

{$form_close}
