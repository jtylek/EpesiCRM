<table border="1">
	{if $type=='Day'}
		<tr>
			<td width="50%" bgcolor="#000000">
				<b><font color="#FFFFFF">&nbsp;{$title.value}</font></b>
			</td>
			<td width="50%" bgcolor="#000000" align="right">
				{if isset($start_time)}
					<b><font color="#FFFFFF">&nbsp;{$start_time.value}</font></b>
				{else}
					<b><font color="#FFFFFF">&nbsp;{$timeless.label}</font></b>
				{/if}
			</td>
		</tr>
	{else}
		<tr>
			<td width="50%" bgcolor="#000000">
				<b><font color="#FFFFFF">&nbsp;{$start_date.details.weekday}, {$start_date.value}</font></b>
			</td>
			<td width="50%" bgcolor="#000000" valign="bottom" align="right">
				{if isset($start_time)}
					<b><font color="#FFFFFF">&nbsp;{$start_time.value}</font></b>
				{else}
					<b><font color="#FFFFFF">&nbsp;{$timeless.label}</font></b>
				{/if}
			</td>
		</tr>
	{/if}
</table>
{if $type!='Day'}
	<table border="1">
		<tr>
			<td width="20%" height="10" bgcolor="#C0C0C0">
				<b>&nbsp;{$title.label}</b>
			</td>
			<td width="80%" height="10" align="left">
				<b>&nbsp;{$title.value}</b>
			</td>
		</tr>
	</table>
{/if}
{if isset($start_time)}
	<table border="1">
		<tr>
			<td width="20%" height="10" bgcolor="#C0C0C0">
				<b>&nbsp;{$start_time.label}</b>
			</td>
			<td width="30%" height="10" align="left">
				<b>&nbsp;{$start_time.value}
				{if isset($end_time)}
					 - {$end_time.value}
				{/if}
				</b>
			</td>
			<td width="20%" height="10" bgcolor="#C0C0C0">
				<b>&nbsp;{$duration.label}</b>
			</td>
			<td width="30%" height="10" align="left">
				<b>&nbsp;{$duration.value}</b>
			</td>
		</tr>
	</table>
{/if}
{if $description.value}
	<table border="1">
		<tr>
			<td width="100%">
				<font size="-1">
					&nbsp;{$description.value}
				</font>
			</td>
		</tr>
	</table>
{/if}
{if !empty($employees.data)}
<table border="1">
	<tr>
		<td width="100%">
			<font size="-1">
				&nbsp;<b>{$employees.main_label}: </b>
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
		<tr>
			<td width="25%" height="10" bgcolor="#C0C0C0">
				&nbsp;<b>{$customers.name_label}</b>
			</td>
			<td width="35%" height="10" bgcolor="#C0C0C0">
				&nbsp;<b>{$customers.company_name}</b>
			</td>
			<td width="20%" height="10" bgcolor="#C0C0C0">
				&nbsp;<b>{$customers.mphone_label}</b>
			</td>
			<td width="20%" height="10" bgcolor="#C0C0C0">
				&nbsp;<b>{$customers.wphone_label}</b>
			</td>
		</tr>
		{assign var=count value=0}
		{foreach item=c from=$customers.data}
			{assign var=count value=$count+1}
			<tr>
				<td width="25%" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
					&nbsp;<font size="-1">{$c.name}</font>
				</td>
				<td width="35%" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
					<font size="-1">
						{assign var=break value=''}
						{foreach item=cmp from=$c.company_name}
							{$break}&nbsp;{$cmp}
							{assign var=break value='<br/>'}
						{/foreach}
					</font>
				</td>
				<td width="20%" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
					&nbsp;<font size="-1">{$c.mphone}</font>
				</td>
				<td width="20%" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
					&nbsp;<font size="-1">{$c.wphone}</font>
				</td>
			</tr>
		{/foreach}
	</table>
{/if}
{if !empty($customers_companies.data)}
	<table border="1">
		<tr>
			<td width="30%" height="10" bgcolor="#C0C0C0">
				&nbsp;<b>{$customers_companies.name_label}</b>
			</td>
			<td width="50%" height="10" bgcolor="#C0C0C0">
				&nbsp;<b>{$customers_companies.address_label}</b>
			</td>
			<td width="20%" height="10" bgcolor="#C0C0C0">
				&nbsp;<b>{$customers_companies.phone_label}</b>
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
				<td width="30%" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
					<font size="-1">&nbsp;{$c.company_name}</font>
				</td>
				<td width="50%" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
					<font size="-1">&nbsp;{$address}</font>
				</td>
				<td width="20%" {if $count%2==0}bgcolor="#F7F7F7"{/if}>
					<font size="-1">&nbsp;{$c.phone}</font>
				</td>
			</tr>
		{/foreach}
	</table>
{/if}
{if isset($created_by)}
	<font size="-1">
		<table border="1">
			<tr>
				<td width="20%" height="9" bgcolor="#C0C0C0">
					&nbsp;<b>{$created_by.label}</b>
				</td>
				<td width="30%" height="9" align="left">
					&nbsp;{$created_by.value}
				</td>
				<td width="20%" height="9" bgcolor="#C0C0C0">
					&nbsp;<b>{$status.label}</b>
				</td>
				<td width="30%" height="9" align="left">
					&nbsp;{$status.value}
				</td>
			</tr>
			<tr>
				<td width="20%" height="9" bgcolor="#C0C0C0">
					&nbsp;<b>{$created_on.label}</b>
				</td>
				<td width="30%" height="9" align="left">
					&nbsp;{$created_on.value}
				</td>
				<td width="20%" height="9" bgcolor="#C0C0C0">
					&nbsp;<b>{$priority.label}</b>
				</td>
				<td width="30%" height="9" align="left">
					&nbsp;{$priority.value}
				</td>
			</tr>
			<tr>
				<td width="20%" height="9" bgcolor="#C0C0C0">
					&nbsp;<b>{$edited_by.label}</b>
				</td>
				<td width="30%" height="9" align="left">
					&nbsp;{$edited_by.value} 
				</td>
				<td width="20%" height="9" bgcolor="#C0C0C0">
					&nbsp;<b>{$access.label}</b>
				</td>
				<td width="30%" height="9" align="left">
					&nbsp;{$access.value}
				</td>
			</tr>
			<tr>
				<td width="20%" height="9" bgcolor="#C0C0C0">
					&nbsp;<b>{$edited_on.label}</b>
				</td>
				<td width="30%" height="9" align="left">
					&nbsp;{$edited_on.value}
				</td>
				<td width="20%" height="9" bgcolor="#C0C0C0">
					&nbsp;<b>{$printed_on.label}</b>
				</td>
				<td width="30%" height="9" align="left">
					&nbsp;{$printed_on.value}
				</td>
			</tr>
		</table>
	</font>
{/if}
