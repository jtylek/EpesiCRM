	<center>

	<br/>
	<h3>{$filename}</h3>
	{$file_size}
	<br/>

	<table id="Utils_Attachment__download" cellspacing="0" cellpadding="0">
		<tr>
			<!-- VIEW -->
			<td valign="top">
				<div class="panel">
					<div class="panel_div">
						{$__link.view.open}
						<div class="icon">
							<div class="div_icon"><img src="{$theme_dir}/Utils/Attachment/view.png" alt="" align="middle" border="0" width="32" height="32"></div>
							<table class="icon_table_text"><tr class="icon_tr_text"><td class="icon_td_text">{$__link.view.text}</td></tr></table>
						</div>
						{$__link.view.close}
					</div>
				</div>
			</td>
			<!-- DOWNLOAD -->
			<td valign="top">
				<div class="panel">
					<div class="panel_div">
						{$__link.download.open}
						<div class="icon">
							<div class="div_icon"><img src="{$theme_dir}/Utils/Attachment/download.png" alt="" align="middle" border="0" width="32" height="32"></div>
							<div style="height: 5px;"></div>
							<table class="icon_table_text"><tr class="icon_tr_text"><td class="icon_td_text">{$__link.download.text}</td></tr></table>
						</div>
						{$__link.download.close}
					</div>
				</div>
			</td>
			<!-- LINK -->
			<td valign="top">
				<div class="panel">
					<div class="panel_div">
						{$__link.link.open}
						<div class="icon">
							<div class="div_icon"><img src="{$theme_dir}/Utils/Attachment/link.png" alt="" align="middle" border="0" width="32" height="32"></div>
							<div style="height: 5px;"></div>
							<table class="icon_table_text"><tr class="icon_tr_text"><td class="icon_td_text">{$__link.link.text}</td></tr></table>
						</div>
						{$__link.link.close}
					</div>
				</div>
			</td>
		</tr>
	</table>

	<table id="Utils_Attachment__download" cellspacing="0" cellpadding="0">
		<tr>
		{assign var=x value=0}
		{foreach item=p key=k from=$custom_getters}
		{assign var=x value=$x+1}
			
			<td valign="top">
				<div class="panel">
					<div class="panel_div">
						{$p.open}
						<div class="icon">
							<div class="div_icon"><img src="{$theme_dir}/{$p.icon}" alt="" align="middle" border="0" width="32" height="32"></div>
							<div style="height: 5px;"></div>
							<table class="icon_table_text"><tr class="icon_tr_text"><td class="icon_td_text">{$p.text}</td></tr></table>
						</div>
						{$p.close}
					</div>
				</div>
			</td>
		{if ($x%4)==0}
		</tr>
		<tr>
		{/if}

	{/foreach}
		</tr>
	</table>
	</center>
