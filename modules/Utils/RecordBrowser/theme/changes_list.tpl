<table class="Utils_RB__changelist" cellspacing="0" style="table-layout: fixed;">
	{if isset($header)}
		<tr class="header">
			<th>
				{$header.0}
			</th>
			<th>
				{$header.1}
			</th>
			<th>
				{$header.2}
			</th>
		</tr>
	{/if}
	{foreach from=$events item=e}
		{if is_string($e.what)}
			{if isset($e.who)}
			<tr>
			{else}
			<tr class="last_row">
			{/if}
				<td colspan="3" class="message">
					{$e.what}
				</td>
		{else}
			{foreach from=$e.what item=r}
				{if isset($e.who)}
				<tr>
				{else}
				<tr class="last_row">
				{/if}
					<td class="field">
						{$r.0}
					</td>
					<td class="data">
						{$r.1}
					</td>
					<td class="data">
						{$r.2}
					</td>
				<tr>
			{/foreach}
		{/if}
		{if isset($e.who)}
			<tr class="last_row">
				<td colspan="2" class="user">
					{$e.who}
				</td>
				<td class="timestamp">
					{$e.when}
				</td>
			</tr>
		{/if}
		</tr>
	{/foreach}
</table>