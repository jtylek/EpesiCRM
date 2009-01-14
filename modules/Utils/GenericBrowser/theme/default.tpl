{php}
	load_js($this->get_template_vars('theme_dir').'/Utils/GenericBrowser/default.js');
{/php}

<div>

<table id="letters-search" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<!-- Custom label -->
			<td>
				{if isset($custom_label)}
				{$custom_label}
				{/if}
			</td>
			<!-- QuickJump -->
			<td class="letters">
				{if isset($letter_links)}
				<div class="abc" onclick="quick_jump_letters('{$id}');">ABC</div>
				<div id="quick_jump_letters_{$id}" class="quick_jump_letters" style="display: none;">
					<!-- SHADOW BEGIN -->
						<div class="layer" style="padding: 10px; width: 540px;">
							<div class="content_shadow">
					<!-- -->

						<div style="background-color: white; height: 20px; padding-top: 2px; padding-left: 2px;">
							{if isset($letter_links)}
							{foreach key=k item=link from=$letter_links}
							{$link}
							{/foreach}
							{/if}
						</div>

					<!-- SHADOW END -->
							</div>
							<div class="shadow-top">
								<div class="left"></div>
								<div class="center"></div>
								<div class="right"></div>
							</div>
							<div class="shadow-middle">
								<div class="left"></div>
								<div class="right"></div>
							</div>
							<div class="shadow-bottom">
								<div class="left"></div>
								<div class="center"></div>
								<div class="right"></div>
							</div>
						</div>
					<!-- -->
				</div>
				{/if}
			</td>
			<!-- Advanced / Simple Search -->
			<td style="text-align: right;">
				{if isset($form_data_search)}
					<div class="IEfix">
					{$form_data_search.javascript}

					<form {$form_data_search.attributes}>
					{$form_data_search.hidden}
					{if isset($form_data_search.search)}
						<table class="Utils_GenericBrowser__search" border="0" cellpadding="0" cellspacing="0">
							<tbody>
								<tr>
									{*<td class="label">{$form_data_search.search.label}</td>*}
									{if isset($form_data_search.show_all)}
										<td class="submit">{$form_data_search.show_all.html}</td>
									{/if}
									<td>{$form_data_search.search.html}</td>
									<td class="submit">{$form_data_search.submit_search.html}</td>
									<td class="advanced">{$adv_search}</td>
								</tr>
							</tbody>
						</table>
					{else}
						{php}
							$cols = $this->get_template_vars('cols');
							$search_fields = $this->get_template_vars('search_fields');
							foreach($cols as $k=>$v){
								if(isset($search_fields[$k]))
									$cols[$k]['label'] = $cols[$k]['label'].$search_fields[$k];
							}
							$this->assign('cols',$cols);
						{/php}
						{if isset($form_data_search.submit_search)}
						<div style="padding-left: 20px; text-align: left;">
						<table class="Utils_GenericBrowser__search" border="0" cellpadding="0" cellspacing="0">
							<tbody>
								<tr>
									{if isset($form_data_search.show_all)}
										<td class="submit">{$form_data_search.show_all.html}</td>
									{/if}
									<td class="submit">{$form_data_search.submit_search.html}</td>
									<td class="advanced">{$adv_search}</td>
								</tr>
							</tbody>
						</table>
						</div>
						{/if}
					{/if}
					</form>
					</div>
				{/if}
			</td>
		</tr>
	</tbody>
</table>

{$table_prefix}

<div class="table">

<!-- SHADOW BEGIN -->
	<div class="layer">
		<div class="content_shadow">
<!-- -->

<div class="margin2px">

{html_table_epesi table_attr='id="Utils_GenericBrowser" cellspacing="0" cellpadding="0" style="width:100%;"' loop=$data cols=$cols row_attrs=$row_attrs}

</div>

<!-- SHADOW END -->
 		</div>
		<div class="shadow-top">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-middle">
			<div class="left"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-bottom">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
	</div>
<!-- -->

</div>

{$table_postfix}

{if isset($form_data_paging)}
{$form_data_paging.javascript}

<form {$form_data_paging.attributes}>
{$form_data_paging.hidden}
{/if}
{if isset($order) || $first || $prev || $summary || isset($form_data_paging.page) || isset($form_data_paging.per_page)}
	<table id="Utils_GenericBrowser__navigation" border="0" cellspacing="0" cellpadding="0">
		<tr>
			<td nowrap style="width: 50%; text-align: left;">
				{if isset($order)}
					{$order}&nbsp;&nbsp;&nbsp;<b>{$reset}</b>&nbsp;&nbsp;&nbsp;
				{/if}
			</td>

			<td nowrap>{if isset($__link.first.open)}{$__link.first.open}&nbsp;<img border="0" src="{$theme_dir}/images/first.png">&nbsp;{$__link.first.text}&nbsp;{$__link.first.close}{/if}</td>
			<td nowrap>{if isset($__link.prev.open)}{$__link.prev.open}&nbsp;<img border="0" src="{$theme_dir}/images/prev.png">&nbsp;{$__link.prev.text}&nbsp;{$__link.prev.close}{/if}</td>
			<td nowrap>&nbsp;&nbsp;&nbsp;{$summary}&nbsp;&nbsp;&nbsp;</td>

			<td nowrap>{if isset($__link.next.open)}{$__link.next.open}&nbsp;{$__link.next.text}&nbsp;<img border="0" src="{$theme_dir}/images/next.png">&nbsp;{$__link.next.close}{/if}</td>
			<td nowrap>{if isset($__link.last.open)}{$__link.last.open}&nbsp;{$__link.last.text}&nbsp;<img border="0" src="{$theme_dir}/images/last.png">&nbsp;{$__link.last.close}{/if}</td>
	
			<td nowrap style="width: 25%; text-align: right;">
				{if isset($form_data_paging.page)}
					&nbsp;&nbsp;&nbsp;{$form_data_paging.page.label}&nbsp;&nbsp;&nbsp;{$form_data_paging.page.html}
				{/if}
			</td>
			<td nowrap style="width: 25%; text-align: right;">
				{if isset($form_data_paging.per_page)}
					&nbsp;&nbsp;&nbsp;{$form_data_paging.per_page.label}&nbsp;&nbsp;&nbsp;{$form_data_paging.per_page.html}
				{/if}
			</td>
		</tr>
	</table>
{/if}

{if isset($form_data_paging)}
</form>
{/if}
</div>
