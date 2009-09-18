<table border="0" height="4" >
	<tr height="4">
		<td width="255" height="4" bgcolor="#FFFFFF">
			<b><font size="-4" color="#FFFFFF">title.value</font></b>
		</td>
	</tr>
</table>
<table border="1">
	{if $type=='Day'}
		<tr>
			<td width="255" bgcolor="#000000">
				<b><font color="#FFFFFF">{$title.value}</font></b>
			</td>
			<td width="255" bgcolor="#000000" align="right">
				{if isset($start_time)}
					<b><font color="#FFFFFF">{$start_time.value}</font></b>
				{else}
					<b><font color="#FFFFFF">{$timeless.label}</font></b>
				{/if}
			</td>
		</tr>
	{else}
		<tr>
			<td width="255" bgcolor="#000000">
				<b><font color="#FFFFFF">{$start_date.details.weekday}, {$start_date.value}</font></b>
			</td>
			<td width="255" bgcolor="#000000" valign="bottom" align="right">
				{if isset($start_time)}
					<b><font color="#FFFFFF">{$start_time.value}</font></b>
				{else}
					<b><font color="#FFFFFF">{$timeless.label}</font></b>
				{/if}
			</td>
		</tr>
	{/if}
</table>
{if $type!='Day'}
	<table border="1">
		<tr>
			<td width="100" height="10" bgcolor="#C0C0C0">
				<b>{$title.label}</b>
			</td>
			<td width="410" height="10" align="left">
				<b>{$title.value}</b>
			</td>
		</tr>
	</table>
{/if}
{if isset($start_time)}
	<table border="1">
		<tr>
			<td width="100" height="10" bgcolor="#C0C0C0">
				<b>{$start_time.label}</b>
			</td>
			<td width="155" height="10" align="left">
				<b>{$start_time.value}
				{if isset($end_time)}
					 - {$end_time.value}
				{/if}
				</b>
			</td>
			<td width="100" height="10" bgcolor="#C0C0C0">
				<b>{$duration.label}</b>
			</td>
			<td width="155" height="10" align="left">
				<b>{$duration.value}</b>
			</td>
		</tr>
	</table>
{/if}
{if $description.value}
	<table border="1">
		<tr>
			<td width="510">
				<font size="-1">
					{$description.value}
				</font>
			</td>
		</tr>
	</table>
{/if}
{if !empty($employees.data)}
<table border="1">
	<tr>
		<td width="510">
			<font size="-1">
				<b>{$employees.main_label}: </b>
				{assign var=dot value=''}
				{foreach item=e from=$employees.data}
					{$dot}{$e.name}
					{assign var=dot value=', '}
				{/foreach}
			</font>
		</td>
	</tr>
</table>
{/if}	
{if !empty($customers.data)}
	{*<br>*}
	<table border="1">
{*		<tr>
			<td width="720" height="10" bgcolor="#C0C0C0">
				<b>{$customers.main_label}</b>
			</td>
		</tr>*}
		<tr>
			<td width="130" height="10" bgcolor="#C0C0C0">
				<b>{$customers.name_label}</b>
			</td>
			<td width="160" height="10" bgcolor="#C0C0C0">
				<b>{$customers.company_name}</b>
			</td>
			<td width="110" height="10" bgcolor="#C0C0C0">
				<b>{$customers.mphone_label}</b>
			</td>
			<td width="110" height="10" bgcolor="#C0C0C0">
				<b>{$customers.wphone_label}</b>
			</td>
		</tr>
		{assign var=count value=0}
		{foreach item=c from=$customers.data}
			{assign var=count value=$count+1}
			<tr>
				<td width="130" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
					<font size="-1">{$c.name}</font>
				</td>
				<td width="160" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
					<font size="-1">
						{assign var=break value=''}
						{foreach item=cmp from=$c.company_name}
							{$break}{$cmp}
							{assign var=break value='<br/>'}
						{/foreach}
					</font>
				</td>
				<td width="110" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
					<font size="-1">{$c.mphone}</font>
				</td>
				<td width="110" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
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
			<td width="155" height="10" bgcolor="#C0C0C0">
				<b>{$customers_companies.name_label}</b>
			</td>
			<td width="245" height="10" bgcolor="#C0C0C0">
				<b>{$customers_companies.address_label}</b>
			</td>
			<td width="110" height="10" bgcolor="#C0C0C0">
				<b>{$customers_companies.phone_label}</b>
			</td>
		</tr>
		{assign var=count value=0}
		{foreach item=c from=$customers_companies.data}
			{assign var=count value=$count+1}
			{php}
				$this->_tpl_vars['address'] = '';
				$tmp = '';
				foreach (array('address_1','address_2','city','zone','postal_code') as $v)
					if (isset($this->_tpl_vars['c'][$v]) && $this->_tpl_vars['c'][$v]!='') {
						$this->_tpl_vars['address'] .= $tmp.$this->_tpl_vars['c'][$v];
						$tmp = ', ';
					}
			{/php}
			<tr>
				<td width="155" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
					<font size="-1">{$c.company_name}</font>
				</td>
				<td width="245" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
					<font size="-1">{$address}</font>
				</td>
				<td width="110" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
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
				<td width="100" height="9" bgcolor="#C0C0C0">
					<b>{$created_by.label}</b>
				</td>
				<td width="155" height="9" align="left">
					{$created_by.value}
				</td>
				<td width="100" height="9" bgcolor="#C0C0C0">
					<b>{$status.label}</b>
				</td>
				<td width="155" height="9" align="left">
					{$status.value}
				</td>
			</tr>
			<tr>
				<td width="100" height="9" bgcolor="#C0C0C0">
					<b>{$created_on.label}</b>
				</td>
				<td width="155" height="9" align="left">
					{$created_on.value}
				</td>
				<td width="100" height="9" bgcolor="#C0C0C0">
					<b>{$priority.label}</b>
				</td>
				<td width="155" height="9" align="left">
					{$priority.value}
				</td>
			</tr>
			<tr>
				<td width="100" height="9" bgcolor="#C0C0C0">
					<b>{$edited_by.label}</b>
				</td>
				<td width="155" height="9" align="left">
					{$edited_by.value} 
				</td>
				<td width="100" height="9" bgcolor="#C0C0C0">
					<b>{$access.label}</b>
				</td>
				<td width="155" height="9" align="left">
					{$access.value}
				</td>
			</tr>
			<tr>
				<td width="100" height="9" bgcolor="#C0C0C0">
					<b>{$edited_on.label}</b>
				</td>
				<td width="155" height="9" align="left">
					{$edited_on.value}
				</td>
				<td width="100" height="9" bgcolor="#C0C0C0">
					<b>{$printed_on.label}</b>
				</td>
				<td width="155" height="9" align="left">
					{$printed_on.value}
				</td>
			</tr>
		</table>
	</font>
{/if}
