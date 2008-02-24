{php}
	load_js('data/Base_Theme/templates/default/Utils_GenericBrowser__default.js');
{/php}

<div>
{if isset($form_data)}
{$form_data.javascript}

<form {$form_data.attributes}>
{$form_data.hidden}
{/if}

<table id="letters-search">
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
						<div class="layer" style="padding: 10px; width: 530px;">
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
				{if isset($form_data.search)}
					<div>
					<table class="Utils_GenericBrowser__search" border="0" cellpadding="0" cellspacing="0">
						<tbody>
							<tr>
								{*<td class="label">{$form_data.search.label}</td>*}
								<td>{$form_data.search.html}</td>
								<td class="submit">{$form_data.submit_search.html}</td>
								<td class="advanced">{$adv_search}</td>
							</tr>
						</tbody>
					</table>
					</div>
				{else}
					{php}
						$cols = $this->get_template_vars('cols');
						$search_fields = $this->get_template_vars('search_fields');
						$i=0;
						foreach($cols as $k=>$v){
							if(!isset($search_fields[$i])) {
								$i++;
								continue;
							}
							$cols[$k]['label'] = $cols[$k]['label'].$search_fields[$i];
							$i++;
						}
						$this->assign('cols',$cols);
					{/php}
					{if isset($form_data.submit_search)}
					<div style="padding-left: 20px; text-align: left;">
					<table class="Utils_GenericBrowser__search" border="0" cellpadding="0" cellspacing="0">
						<tbody>
							<tr>
								<td class="submit">{$form_data.submit_search.html}</td>
								<td class="advanced">{$adv_search}</td>
							</tr>
						</tbody>
					</table>
					</div>
					{/if}
				{/if}
			</td>
		</tr>
	</tbody>
</table>

<!-- SHADOW BEGIN -->
	<div class="layer">
		<div class="content_shadow">
<!-- -->

<div class="margin2px">

{html_table_epesi table_attr='id="Utils_GenericBrowser" cellspacing="0" cellpadding="0" style="width:100%;"' loop=$data cols=$cols}

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

<table id="Utils_GenericBrowser__navigation" border="0" cellspacing="0" cellpadding="0">
	<tr>
		<td nowrap style="width: 50%; text-align: left;">
			{if isset($order)}
				{$order}&nbsp;&nbsp;&nbsp;<b>{$reset}</b>&nbsp;&nbsp;&nbsp;
			{/if}
		</td>

		<td nowrap>{if $first}&nbsp;<img src="{$theme_dir}/images/first.png">&nbsp;{$first}&nbsp;{/if}</td>
		<td nowrap>{if $prev}&nbsp;<img src="{$theme_dir}/images/prev.png">&nbsp;{$prev}&nbsp;{/if}</td>

		<td nowrap>&nbsp;&nbsp;&nbsp;{$summary}&nbsp;&nbsp;&nbsp;</td>

		<td nowrap>{if $next}&nbsp;{$next}&nbsp;<img src="{$theme_dir}/images/next.png">&nbsp;{/if}</td>
		<td nowrap>{if $last}&nbsp;{$last}&nbsp;<img src="{$theme_dir}/images/last.png">&nbsp;{/if}</td>

		<td nowrap style="width: 50%; text-align: right;">
			{if isset($form_data.per_page)}
				&nbsp;&nbsp;&nbsp;{$form_data.per_page.label}&nbsp;&nbsp;&nbsp;{$form_data.per_page.html}
			{/if}
		</td>
	</tr>
</table>

{if isset($form_data)}
</form>
{/if}
</div>
