{if $type == 'images'}
	<table class=gallery>
		<tr>
			<td colspan=2 class=path>
				{$path}
		</tr>
		<tr>
			<td colspan=2 class=dirs align=center>		
				<table align=center><tr>
				{assign var="counter" value=1}
				{foreach from=$dirs key=k item=v}
					{if $counter == 6}
						<td>{$v}</td>
						</tr><tr>
						{assign var="counter" value=1}
					{else}
						<td>{$v}<td>
						{assign var="counter" value=$counter+1}
					{/if}
				{/foreach}
				</tr></table>
			</td>
		</tr>
		<tr>
			<td class=tree width=35%>
				{$tree}
					<br>
				{$other}
			</td>
			<td class=images>
				{$images}
			</td>
		</tr>
	</table>
{elseif $type == 'upload'}
	<form {$form_data.attributes}>
	{$form_data.hidden}
		<!-- Display the fields -->
		<table cellspacing=0>
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
				<td>
					{$form_data.submit_button.html}
				</td>
			</tr>
		</table>
	</form>
{elseif $type == 'share'}
	<form {$form_data.attributes}>
	{$form_data.hidden}
		<!-- Display the fields -->
		<table class=Apps_Gallery cellspacing=0>
			<tr>
				<td class=header_tail>
					<span align=left class=header>{$form_data.header.share}</span>
				</td>
			</tr>
			<tr>
				<td>
					{$tree}
				</td>
			</tr>
			<tr>
				<td>
					{$form_data.submit_button.html}
				</td>
			</tr>
		</table>
	</form>
{elseif $type == 'rm_folder'}
	<form {$form_data.attributes}>
	{$form_data.hidden}
		<!-- Display the fields -->
		<table class=Apps_Gallery cellspacing=0>
			<tr>
				<td class=header_tail>
					<span align=left class=header>{$form_data.header.rm_folder}</span>
				</td>
			</tr>
			<tr>
				<td>
					{$tree}
				</td>
			</tr>
			<tr>
				<td>
					{$form_data.submit_button.html}
				</td>
			</tr>
		</table>
	</form>
{elseif $type == 'mk_folder'}
	<form {$form_data.attributes}>
	{$form_data.hidden}
		<!-- Display the fields -->
		<table class=Apps_Gallery cellspacing=0>
			<tr>
				<td class=header_tail>
					<span align=left class=header>{$form_data.header.mk_folder}</span>
				</td>
			</tr>
			<tr>
				<td>
					{$tree}
				</td>
			</tr>
			<tr>
				<td>
					{$form_data.new.label}
					{$form_data.new.html}
				</td>
			</tr>
			<tr>
				<td>
					{$form_data.submit_button.html}
				</td>
			</tr>
		</table>
	</form>
{/if}