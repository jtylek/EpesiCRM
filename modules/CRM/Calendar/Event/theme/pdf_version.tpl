<table border="1">
	<tr>
		<td width="720" height="10">

<table border="1">
	{if $type=='Day'}
		<tr>
			<td width="360" height="10" bgcolor="#000000">
				<font color="#FFFFFF">{$title.value}</font>
			</td>
			<td width="360" height="10" bgcolor="#000000" align="right">
				{if isset($start_time)}
					<font color="#FFFFFF">{$start_time.value}</font>
				{else}
					<font color="#FFFFFF">{$timeless.label}</font>
				{/if}
			</td>
		</tr>
	{else}
		<tr>
			<td width="360" height="10" bgcolor="#000000">
				<font color="#FFFFFF">{$start_date.details.weekday}, {$start_date.value}</font>
			</td>
			<td width="360" height="10" bgcolor="#000000" align="right">
				{if isset($start_time)}
					<font color="#FFFFFF">{$start_time.value}</font>
				{else}
					<font color="#FFFFFF">{$timeless.label}</font>
				{/if}
			</td>
		</tr>
	{/if}
</table>
{if $type!='Day'}
	<table border="1">
		<tr>
			<td width="140" height="10" bgcolor="#C0C0C0">
				<b>{$title.label}</b>
			</td>
			<td width="580" height="10" align="left">
				<b>{$title.value}</b>
			</td>
		</tr>
	</table>
{/if}
{if isset($start_time)}
	<table border="1">
		<tr>
			<td width="140" height="10" bgcolor="#C0C0C0">
				<b>{$start_time.label}</b>
			</td>
			<td width="220" height="10" align="left">
				<b>{$start_time.value}
				{if isset($end_time)}
					&nbsp;-&nbsp;{$end_time.value}
				{/if}
				</b>
			</td>
			<td width="140" height="10" bgcolor="#C0C0C0">
				<b>{$duration.label}</b>
			</td>
			<td width="220" height="10" align="left">
				<b>{$duration.value}</b>
			</td>
		</tr>
	</table>
{/if}
{*
	<table border="1">
		<tr>
			<td width="720" height="10" bgcolor="#C0C0C0">
				<b>{$description.label}</font>
			</td>
		</tr>
	</table>
*}
{if $description.value}
	<font size="-1">
		{$description.value}
	</font>
	<br>
{/if}
{*
<table border="1">
	<tr>
		<td width="720" height="10" bgcolor="#C0C0C0">
			<b>{$employees.main_label}</font>
		</td>
	</tr>
	<tr>
		<td width="330" height="10" bgcolor="#C0C0C0">
			{$employees.name_label}
		</td>
		<td width="130" height="10" bgcolor="#C0C0C0">
			{$employees.mphone_label}
		</td>
		<td width="130" height="10" bgcolor="#C0C0C0">
			{$employees.wphone_label}
		</td>
		<td width="130" height="10" bgcolor="#C0C0C0">
			{$employees.hphone_label}
		</td>
	</tr>
	{assign var=count value=0}
	{foreach item=e from=$employees.data}
		{assign var=count value=$count+1}
		<tr>
			<td width="330" height="9" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
				<font size="-1">{$e.name}</font>
			</td>
			<td width="130" height="9" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
				<font size="-1">{$e.mphone}</font>
			</td>
			<td width="130" height="9" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
				<font size="-1">{$e.wphone}</font>
			</td>
			<td width="130" height="9" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
				<font size="-1">{$e.hphone}</font>
			</td>
		</tr>
	{/foreach}
</table>
*}
<font size="-1">
	<b>{$employees.main_label}:&nbsp;</b>
	{assign var=dot value=''}
	{foreach item=e from=$employees.data}
		{$dot}{$e.name}
		{assign var=dot value=', '}
	{/foreach}
	<br>
</font>
	
{if !empty($customers.data)}
	{*<br>*}
	<table border="1">
{*		<tr>
			<td width="720" height="10" bgcolor="#C0C0C0">
				<b>{$customers.main_label}</b>
			</td>
		</tr>*}
		<tr>
			<td width="180" height="10" bgcolor="#C0C0C0">
				<b>{$customers.name_label}</b>
			</td>
			<td width="240" height="10" bgcolor="#C0C0C0">
				<b>{$customers.company_name}</b>
			</td>
			<td width="150" height="10" bgcolor="#C0C0C0">
				<b>{$customers.mphone_label}</b>
			</td>
			<td width="150" height="10" bgcolor="#C0C0C0">
				<b>{$customers.wphone_label}</b>
			</td>
		</tr>
		{assign var=count value=0}
		{foreach item=c from=$customers.data}
			{assign var=count value=$count+1}
			{if strlen($c.company_name)>36}
				{assign var=height value=23}
			{else}
				{assign var=height value=11}
			{/if}
			<tr>
				<td width="180" height="{$height}" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
					<font size="-1">{$c.name}</font>
				</td>
				<td width="240" height="{$height}" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
					<font size="-1">{$c.company_name}</font>
				</td>
				<td width="150" height="{$height}" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
					<font size="-1">{$c.mphone}</font>
				</td>
				<td width="150" height="{$height}" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
					<font size="-1">{$c.wphone}</font>
				</td>
			</tr>
		{/foreach}
	</table>
{/if}
{if !empty($customers_companies.data)}
	{*<table><td height="10"><tr></tr></td></table>
	<br>*}
	<table border="1">
{*		<tr>
			<td width="720" height="10" bgcolor="#C0C0C0">
				<b>{$customers_companies.main_label}</font>
			</td>
		</tr>*}
		<tr>
			<td width="200" height="10" bgcolor="#C0C0C0">
				<b>{$customers_companies.name_label}</b>
			</td>
			<td width="370" height="10" bgcolor="#C0C0C0">
				<b>{$customers_companies.address_label}</b>
			</td>
			<td width="150" height="10" bgcolor="#C0C0C0">
				<b>{$customers_companies.phone_label}</b>
			</td>
		</tr>
		{assign var=count value=0}
		{foreach item=c from=$customers_companies.data}
			{assign var=count value=$count+1}
			{php}
				$this->_tpl_vars['address'] = '';
				$tmp = '';
				foreach (array('address_1','address_2','city','state','postal_code') as $v)
					if (isset($this->_tpl_vars['c'][$v]) && $this->_tpl_vars['c'][$v]!='') {
						$this->_tpl_vars['address'] .= $tmp.$this->_tpl_vars['c'][$v];
						$tmp = ', ';
					}
			{/php}
			{if strlen($c.company_name)>30 || strlen($address)>55}
				{assign var=height value=23}
			{else}
				{assign var=height value=11}
			{/if}
			<tr>
				<td width="200" height="{$height}" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
					<font size="-1">{$c.company_name}</font>
				</td>
				<td width="370" height="{$height}" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
					<font size="-1">{$address}</font>
				</td>
				<td width="150" height="{$height}" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
					<font size="-1">{$c.phone}</font>
				</td>
			</tr>
		{/foreach}
	</table>
{/if}
{if isset($created_by)}
	<font size="-1">
		{*<table><td height="10"><tr></tr></td></table>
		<br>*}
		<table border="1">
			<tr>
				<td width="140" height="9" bgcolor="#C0C0C0">
					<b>{$created_by.label}</b>
				</td>
				<td width="220" height="9" align="left">
					{$created_by.value}
				</td>
				<td width="140" height="9" bgcolor="#C0C0C0">
					<b>{$status.label}</b>
				</td>
				<td width="220" height="9" align="left">
					{$status.value}
				</td>
			</tr>
			<tr>
				<td width="140" height="9" bgcolor="#C0C0C0">
					<b>{$created_on.label}</b>
				</td>
				<td width="220" height="9" align="left">
					{$created_on.value}
				</td>
				<td width="140" height="9" bgcolor="#C0C0C0">
					<b>{$priority.label}</b>
				</td>
				<td width="220" height="9" align="left">
					{$priority.value}
				</td>
			</tr>
			<tr>
				<td width="140" height="9" bgcolor="#C0C0C0">
					<b>{$edited_by.label}</b>
				</td>
				<td width="220" height="9" align="left">
					{$edited_by.value} 
				</td>
				<td width="140" height="9" bgcolor="#C0C0C0">
					<b>{$access.label}</b>
				</td>
				<td width="220" height="9" align="left">
					{$access.value}
				</td>
			</tr>
			<tr>
				<td width="140" height="9" bgcolor="#C0C0C0">
					<b>{$edited_on.label}</b>
				</td>
				<td width="220" height="9" align="left">
					{$edited_on.value}
				</td>
				<td width="140" height="9" bgcolor="#C0C0C0">
					<b>{$printed_on.label}</b>
				</td>
				<td width="220" height="9" align="left">
					{$printed_on.value}
				</td>
			</tr>
		</table>
	</font>
{/if}
		</td>
	</tr>
</table>