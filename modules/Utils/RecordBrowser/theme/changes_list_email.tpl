<table class="Utils_RB__changelist" cellspacing="0" cellpadding="4" style="table-layout: fixed; width: 400px; border: 1px solid black; background-color: #e6ecf2">
	{if isset($header)}
		<tr class="header" style="background-color: lightblue;">
			<th style="width: 30%">
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
				<td colspan="3" class="message" style="	text-align: center;	font-weight: bold; border-right: 1px solid darkgray; border-bottom: 1px solid darkgray;">
					{$e.what}
				</td>
            </tr>
		{else}
			{foreach from=$e.what item=r}
				{if isset($e.who)}
				<tr>
				{else}
				<tr class="last_row">
				{/if}
					<td class="field" style="border-right: 1px solid darkgray; border-bottom: 1px solid darkgray; width: 30%">
						{$r.0}
					</td>
					<td class="data" style="border-right: 1px solid darkgray; border-bottom: 1px solid darkgray; width: 35%; word-wrap: break-word;">
						{$r.1}
					</td>
					<td class="data" style="border-right: 1px solid darkgray; border-bottom: 1px solid darkgray; width: 35%; word-wrap: break-word;">
						{$r.2}
					</td>
				</tr>
			{/foreach}
		{/if}
		{if isset($e.who)}
			<tr class="last_row" style="color: #777; font-size: 80%;">
				<td colspan="2" class="user">
					{$e.who}
				</td>
				<td class="timestamp" style="text-align: right">
					{$e.when}
				</td>
			</tr>
		{/if}
	{/foreach}
</table>
{if isset($record)}
    <table style="font-size: 80%; border: 1px solid black; table-layout: fixed; width: 400px; background-color: #e6ecf2;" cellspacing="0" cellpadding="1">
        <tr style="height: 0px; background-color: lightblue"><td style="width: 30%"></td><td></td></tr>
        <tr>
            <td style="background-color: lightblue; font-size: 120%; font-weight:bold; padding-bottom: 4px; text-align: center; border-bottom: 1px solid darkgray" colspan="2">{"Record Information"|t}</td>
        </tr>
        {foreach from=$record item=value key=label}
            <tr>
                <td style="font-weight: bold; border-right: 1px solid darkgray; border-bottom: 1px solid darkgray; background-color: lightblue; text-align: center">{$label}</td>
                <td style="border-bottom: 1px solid darkgray; padding-left: 0.5em">{$value}</td>
            </tr>
        {/foreach}
    </table>
{/if}