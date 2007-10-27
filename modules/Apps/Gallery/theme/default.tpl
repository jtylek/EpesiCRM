{if $type == 'images'}
	<table id="Apps_Gallery" cellpadding="0" cellspacing="0" border="0">
		<tr>
			<td colspan="2" class="path">
				{$path}
			</td>	
		</tr>
		<tr>
			<td colspan="2" class="dirs" align="center">		
				<table align="center" style="color: #B3B3B3;">
				{assign var="counter" value=1}
				{foreach from=$dirs key=k item=v}
					{if $counter % 6 == 1}
						<tr><td>{$v}</td>
					{elseif  $counter % 6 == 0}
						<td>|</td><td>{$v}</td></tr>
					{else}
						<td>|</td><td>{$v}</td>
					{/if}
					{assign var="counter" value=$counter+1}
				{/foreach}
				{if $counter % 6 > 0}
					</tr>
				{/if}
				</table>
			</td>
		</tr>
		<tr>
			<td class="tree">
				{$tree}
					<br>
				{$other}
			</td>
			<td class="images">
				{$images}
			</td>
		</tr>
	</table>
	
{elseif $type == 'upload'}
	<form {$form_data.attributes}>
	{$form_data.hidden}
		<!-- Display the fields -->
		<table id="Apps_Gallery__form" cellspacing="0">
			<tr>
				<td>
					{$form_data.header.upload}
				</td>
			</tr>
			<tr>
				<td>
					{$tree}
				</td>
			</tr>
			<tr>
				<td class="button">
					{$form_data.submit_button.html}
				</td>
			</tr>
		</table>
	</form>
	
{elseif $type == 'share'}
	<form {$form_data.attributes}>
	{$form_data.hidden}
		<!-- Display the fields -->
		<table id="Apps_Gallery__form" cellspacing="0">
			<tr>
				<td class="header_tail">
					<span align="left" class="header">{$form_data.header.share}</span>
				</td>
			</tr>
			<tr>
				<td>
					{$tree}
				</td>
			</tr>
			<tr>
				<td class="button">
					{$form_data.submit_button.html}
				</td>
			</tr>
		</table>
	</form>
	
{elseif $type == 'rm_folder'}
	<form {$form_data.attributes}>
	{$form_data.hidden}
		<!-- Display the fields -->
		<table id="Apps_Gallery__form" cellspacing="0">
			<tr>
				<td class="header_tail">
					<span align="left" class="header">{$form_data.header.rm_folder}</span>
				</td>
			</tr>
			<tr>
				<td>
					{$tree}
				</td>
			</tr>
			<tr>
				<td class="button">
					{$form_data.submit_button.html}
				</td>
			</tr>
		</table>
	</form>
	
{elseif $type == 'mk_folder'}
	<form {$form_data.attributes}>
	{$form_data.hidden}
		<!-- Display the fields -->
		<table id="Apps_Gallery__form" cellspacing="0">
			<tr>
				<td class="header_tail">
					<span align="left" class="header">{$form_data.header.mk_folder}</span>
				</td>
			</tr>
			<tr>
				<td>
					{$tree}
				</td>
			</tr>
			<tr>
				<td>
					{$form_data.new.error}
					{$form_data.new.label}
					{$form_data.new.html}
				</td>
			</tr>
			<tr>
				<td class="button">
					{$form_data.submit_button.html}
				</td>
			</tr>
		</table>
	</form>
{/if}