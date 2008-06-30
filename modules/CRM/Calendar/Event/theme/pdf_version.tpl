<table border="1">
	<tr>
		<td width="250" height="12" bgcolor="#444444">
			<font color="#FFFFFF">{$title.label}</font>
		</td>
		<td width="470" height="12" align="left">
			{$title.value}
		</td>
	</tr>
	<tr>
		<td width="250" height="12" bgcolor="#444444">
			<font color="#FFFFFF">{$start_date.label}</font>
		</td>
		<td width="470" height="12" align="left">
			{$start_date.value}
		</td>
	</tr>
	{if isset($start_time)}
		<tr>
			<td width="250" height="12" bgcolor="#444444">
				<font color="#FFFFFF">{$start_time.label}</font>
			</td>
			<td width="470" height="12" align="left">
				{$start_time.value} 
			</td>
		</tr>
	{/if}
	{if isset($end_date)}
		<tr>
			<td width="250" height="12" bgcolor="#444444">
				<font color="#FFFFFF">{$end_date.label}</font>
			</td>
			<td width="470" height="12" align="left">
				{$end_date.value} 
			</td>
		</tr>
	{/if}
	{if isset($end_time)}
		<tr>
			<td width="250" height="12" bgcolor="#444444">
				<font color="#FFFFFF">{$end_time.label}</font>
			</td>
			<td width="470" height="12" align="left">
				{$end_time.value} 
			</td>
		</tr>
	{/if}
	{if isset($duration)}
		<tr>
			<td width="250" height="12" bgcolor="#444444">
				<font color="#FFFFFF">{$duration.label}</font>
			</td>
			<td width="470" height="12" align="left">
				{$duration.value} 
			</td>
		</tr>
	{/if}
</table>
<br>
<table border="1">
	<tr>
		<td width="720" height="12" bgcolor="#444444">
			<font color="#FFFFFF">{$description.label}</font>
		</td>
	</tr>
</table>
<font size="-1">
	{$description.value}
</font>
<br>
<table border="1">
	<tr>
		<td width="720" height="12" bgcolor="#444444">
			<font color="#FFFFFF">{$employees.main_label}</font>
		</td>
	</tr>
	<tr>
		<td width="420" height="12" bgcolor="#C6C6C6">
			{$employees.name_label}
		</td>
		<td width="100" height="12" bgcolor="#C6C6C6">
			{$employees.mphone_label}
		</td>
		<td width="100" height="12" bgcolor="#C6C6C6">
			{$employees.wphone_label}
		</td>
		<td width="100" height="12" bgcolor="#C6C6C6">
			{$employees.hphone_label}
		</td>
	</tr>
	{assign var=count value=0}
	{foreach item=e from=$employees.data}
		{assign var=count value=$count+1}
		<tr>
			<td width="420" height="10" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
				<font size="-1">{$e.name}</font>
			</td>
			<td width="100" height="10" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
				<font size="-1">{$e.mphone}</font>
			</td>
			<td width="100" height="10" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
				<font size="-1">{$e.wphone}</font>
			</td>
			<td width="100" height="10" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
				<font size="-1">{$e.hphone}</font>
			</td>
		</tr>
	{/foreach}
</table>
<br>
<table border="1">
	<tr>
		<td width="720" height="12" bgcolor="#444444">
			<font color="#FFFFFF">{$customers.main_label}</font>
		</td>
	</tr>
	<tr>
		<td width="140" height="12" bgcolor="#C6C6C6">
			{$customers.name_label}
		</td>
		<td width="180" height="12" bgcolor="#C6C6C6">
			{$customers.company_name}
		</td>
		<td width="100" height="12" bgcolor="#C6C6C6">
			{$customers.company_phone}
		</td>
		<td width="100" height="12" bgcolor="#C6C6C6">
			{$customers.mphone_label}
		</td>
		<td width="100" height="12" bgcolor="#C6C6C6">
			{$customers.wphone_label}
		</td>
		<td width="100" height="12" bgcolor="#C6C6C6">
			{$customers.hphone_label}
		</td>
	</tr>
	{assign var=count value=0}
	{foreach item=c from=$customers.data}
		{assign var=count value=$count+1}
		<tr>
			<td width="140" height="10" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
				<font size="-1">{$c.name}</font>
			</td>
			<td width="180" height="10" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
				<font size="-1">{$c.company_name}</font>
			</td>
			<td width="100" height="10" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
				<font size="-1">{$c.cphone}</font>
			</td>
			<td width="100" height="10" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
				<font size="-1">{$c.mphone}</font>
			</td>
			<td width="100" height="10" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
				<font size="-1">{$c.wphone}</font>
			</td>
			<td width="100" height="10" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
				<font size="-1">{$c.hphone}</font>
			</td>
		</tr>
	{/foreach}
</table>
<br>
<table border="1">
	<tr>
		<td width="250" height="12" bgcolor="#444444">
			<font color="#FFFFFF">{$created_by.label}</font>
		</td>
		<td width="470" height="12" align="left">
			{$created_by.value}
		</td>
	</tr>
	<tr>
		<td width="250" height="12" bgcolor="#444444">
			<font color="#FFFFFF">{$created_on.label}</font>
		</td>
		<td width="470" height="12" align="left">
			{$created_on.value}
		</td>
	</tr>
	{if isset($edited_by)}
		<tr>
			<td width="250" height="12" bgcolor="#444444">
				<font color="#FFFFFF">{$edited_by.label}</font>
			</td>
			<td width="470" height="12" align="left">
				{$edited_by.value} 
			</td>
		</tr>
		<tr>
			<td width="250" height="12" bgcolor="#444444">
				<font color="#FFFFFF">{$edited_on.label}</font>
			</td>
			<td width="470" height="12" align="left">
				{$edited_on.value}
			</td>
		</tr>
	{/if}
	<tr>
		<td width="250" height="12" bgcolor="#444444">
			<font color="#FFFFFF">{$access.label}</font>
		</td>
		<td width="470" height="12" align="left">
			{$access.value}
		</td>
	</tr>
	<tr>
		<td width="250" height="12" bgcolor="#444444">
			<font color="#FFFFFF">{$priority.label}</font>
		</td>
		<td width="470" height="12" align="left">
			{$priority.value}
		</td>
	</tr>
	<tr>
		<td width="250" height="12" bgcolor="#444444">
			<font color="#FFFFFF">{$status.label}</font>
		</td>
		<td width="470" height="12" align="left">
			{$status.value}
		</td>
	</tr>
</table>