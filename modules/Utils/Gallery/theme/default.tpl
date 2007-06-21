<div id="utils_gallery__conteiner" class=utils_gallery__conteiner>
	{if $style=='preview'}
		<table class="Utils_Gallery" cellspacing="2">
			<tr>
				<td align=center>
						<span style="display: none;">{$prev_list}</span>
						{$preview.open_link}
						<div class="utils_gallery_preview">
							{$preview.img}<br>
							{$preview.name}
						</div>
						{$preview.close_link}
						<span style="display: none;">{$next_list}</span>
				</td>
			</tr>
			
			{* buttons *}
			<tr>
				<td align=center>
					{foreach from=$buttons key=k item=v}
						{$v}
					{/foreach}
				</td>
			</tr>
			
			{* buttons *}
			<tr>
				<td>
					<div class="utils_gallery_prewiev_list">
						<table><tr>
						{foreach from=$image_list key=k item=v}
							<td class="utils_gallery_prewiev_list_item">
								{$v.open_link}
								{$v.img}<br>
								{$v.name}
								{$v.close_link}
							</td>
						{/foreach}
						</tr></table>
					</div>
				</td>
			</tr>
		</table>
		
	{elseif $style=='slideshow'}
	<center><br>
		<div>
		{foreach from=$buttons key=k item=v}
			{$v}
		{/foreach}
		</div>
		<br><br>
		{$preview}
	</center>
	
	{else}
		<table class="Utils_Gallery"><tr>
			{assign var="counter" value=1}
			{foreach from=$image_list key=k item=v}
				{if $counter == 4}
					<td class="utils_gallery_item">
						{$v.open_link}
						{$v.img}<br>
						{$v.name}
						{$v.close_link}
					</td>
					</tr><tr>
					{assign var="counter" value=1}
				{else}
					<td class="utils_gallery_item">
						{$v.open_link}
						{$v.img}<br>
						{$v.name}
						{$v.close_link}
					<td>
					{assign var="counter" value=$counter+1}
				{/if}
			{/foreach}
		</tr></table>
	{/if}
</div>

