<center>
<br/>
<table id="Utils_FileStorage__download_info" cellpadding="0" style="width:300px; border-spacing: 3px;">
	<tr>
		<td class="epesi_label" style="width:30%;">
			{$labels.filename}
		</td>
		<td class="epesi_data static_field" style="width:70%;white-space: nowrap;overflow: hidden;text-overflow: ellipsis;">
			{$filename}
		</td>
	</tr>
	<tr>
		<td class="epesi_label" style="width:30%;">
			{$labels.file_size}
		</td>
		<td class="epesi_data static_field" style="width:70%;">
			{$file_size}
		</td>
	</tr>
</table>
<br/>

<div id="{$download_options_id}">
	<table id="Utils_FileStorage__download" cellspacing="0" cellpadding="0">
		<tr>
			<!-- VIEW -->
			<td valign="top">
				{$__link.view.open}
					<div class="epesi_big_button">
						<img src="{$theme_dir}/Utils/FileStorage/view.png" alt="" align="middle" border="0" width="32" height="32">
						<span>{$__link.view.text}</span>
					</div>
				{$__link.view.close}
			</td>
			<!-- DOWNLOAD -->
			<td valign="top">
				{$__link.download.open}
					<div class="epesi_big_button">
						<img src="{$theme_dir}/Utils/FileStorage/download.png" alt="" align="middle" border="0" width="32" height="32">
						<span>{$__link.download.text}</span>
					</div>
				{$__link.download.close}
			</td>
			<!-- HISTORY -->
			{if $__link.history}
			<td valign="top">
				{$__link.history.open}
					<div class="epesi_big_button">
						<img src="{$theme_dir}/Utils/FileStorage/history.png" alt="" align="middle" border="0" width="32" height="32">
						<span>{$__link.history.text}</span>
					</div>
				{$__link.history.close}
			</td>
			{/if}
            {if isset($link)}
			<!-- LINK -->
			<td valign="top">
				{$__link.link.open}
					<div class="epesi_big_button">
						<img src="{$theme_dir}/Utils/FileStorage/link.png" alt="" align="middle" border="0" width="32" height="32">
						<span>{$__link.link.text}</span>
					</div>
				{$__link.link.close}
			</td>
            {/if}
		</tr>
	</table>

	{if $custom_getters}
	<table id="Utils_FileStorage__download" cellspacing="0" cellpadding="0">
		<tr>
		{assign var=x value=0}
		{foreach item=p key=k from=$custom_getters}
		{assign var=x value=$x+1}
			
			<td valign="top">
				{$p.open}
					<div class="epesi_big_button">
						<img src="{$p.icon}" alt="" align="middle" border="0" width="32" height="32">
						<span>{$p.text}</span>
					</div>
				{$p.close}
			</td>
		{if ($x%4)==0}
		</tr>
		<tr>
		{/if}

	{/foreach}
		</tr>
	</table>
	{/if}
</div>
</center>
