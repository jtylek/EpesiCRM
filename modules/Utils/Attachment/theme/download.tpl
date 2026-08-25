<center>
<br/>
<div id="Utils_Attachment__download_info" style="width:300px; display: flex; flex-direction: column; row-gap: 3px;">
	<div style="display: flex; column-gap: 3px;">
		<div class="epesi_label" style="flex: 0 0 30%;">
			{$labels.filename}
		</div>
		<div class="epesi_data static_field" style="flex: 1 1 auto; min-width: 0; white-space: nowrap;overflow: hidden;text-overflow: ellipsis;">
			{$filename}
		</div>
	</div>
	<div style="display: flex; column-gap: 3px;">
		<div class="epesi_label" style="flex: 0 0 30%;">
			{$labels.file_size}
		</div>
		<div class="epesi_data static_field" style="flex: 1 1 auto; min-width: 0;">
			{$file_size}
		</div>
	</div>
</div>
<br/>

<div id="{$download_options_id}">
	<div id="Utils_Attachment__download" style="display: flex; flex-wrap: wrap;">
			<!-- VIEW -->
			{$__link.view.open}
				<div class="epesi_big_button">
					<img src="{$theme_dir}/Utils/Attachment/view.png" alt="" align="middle" border="0" width="32" height="32">
					<span>{$__link.view.text}</span>
				</div>
			{$__link.view.close}
			<!-- DOWNLOAD -->
			{$__link.download.open}
				<div class="epesi_big_button">
					<img src="{$theme_dir}/Utils/Attachment/download.png" alt="" align="middle" border="0" width="32" height="32">
					<span>{$__link.download.text}</span>
				</div>
			{$__link.download.close}
			<!-- HISTORY -->
			{$__link.history.open}
				<div class="epesi_big_button">
					<img src="{$theme_dir}/Utils/Attachment/history.png" alt="" align="middle" border="0" width="32" height="32">
					<span>{$__link.history.text}</span>
				</div>
			{$__link.history.close}
            {if isset($link)}
			<!-- LINK -->
			{$__link.link.open}
				<div class="epesi_big_button">
					<img src="{$theme_dir}/Utils/Attachment/link.png" alt="" align="middle" border="0" width="32" height="32">
					<span>{$__link.link.text}</span>
				</div>
			{$__link.link.close}
            {/if}
	</div>

	{if $custom_getters}
	{* Was a <table> hand-wrapping every 4 icons into a new <tr> - flex-wrap
	   replaces that, same recipe used app-wide for this pattern (see
	   AI-shared/adminlte-theme.md). *}
	<div id="Utils_Attachment__download" style="display: flex; flex-wrap: wrap;">
		{foreach item=p key=k from=$custom_getters}
			{$p.open}
				<div class="epesi_big_button">
					<img src="{$p.icon}" alt="" align="middle" border="0" width="32" height="32">
					<span>{$p.text}</span>
				</div>
			{$p.close}
		{/foreach}
	</div>
	{/if}
</div>

{if isset($save_options_id)}
	<div id="{$save_options_id}" style="display: none;">
		<div id="Utils_Attachment__download" style="display: flex; flex-wrap: wrap;">
				<!-- SAVE -->
				{$__link.save.open}
					<div class="epesi_big_button">
						<img src="{$theme_dir}/Utils/Attachment/save.png" alt="" align="middle" border="0" width="32" height="32">
						<span>{$__link.save.text}</span>
					</div>
				{$__link.save.close}
				<!-- DISCARD -->
				{$__link.discard.open}
					<div class="epesi_big_button">
						<img src="{$theme_dir}/Utils/Attachment/discard.png" alt="" align="middle" border="0" width="32" height="32">
						<span>{$__link.discard.text}</span>
					</div>
				{$__link.discard.close}
		</div>
	</div>
{/if}

</center>
