
<table class="Utils_RecordBrowser__table" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td style="width:100px;">
				<div class="name">
					<img alt="&nbsp;" class="icon" src="{$icon}" width="32" height="32" border="0">
					<div class="label">{$caption}</div>
				</div>
			</td>
			<td class="required_fav_info">
				&nbsp;*&nbsp;{$required_note}
				{if isset($subscription_tooltip)}
					&nbsp;&nbsp;&nbsp;{$subscription_tooltip}
				{/if}
				{if isset($fav_tooltip)}
					&nbsp;&nbsp;&nbsp;{$fav_tooltip}
				{/if}
				{if isset($info_tooltip)}
					&nbsp;&nbsp;&nbsp;{$info_tooltip}
				{/if}
				{if isset($clipboard_tooltip)}
					&nbsp;&nbsp;&nbsp;{$clipboard_tooltip}
				{/if}
				{if isset($history_tooltip)}
					&nbsp;&nbsp;&nbsp;{$history_tooltip}
				{/if}
				{foreach item=n from=$new}
					&nbsp;&nbsp;&nbsp;{$n}
				{/foreach}
			</td>
		</tr>
	</tbody>
</table>

{if isset($click2fill)}
    {$click2fill}
{/if}


<!-- SHADOW BEGIN -->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="css3_content_shadow">
<!-- -->

<div class="Utils_RecordBrowser__container">

{* Outside table *}
<table class="Utils_RecordBrowser__View_entry" cellpadding="0" cellspacing="0" border="0">
	<tbody>
		<tr>
			<td class="left-column">
				<table border="0" cellpadding="0" cellspacing="0" class="{if $action == 'view'}view{else}edit{/if}">
					<tbody>
						{* create new company *}
						{if isset($form_data.create_company)}
						<tr>
							<td class="label" nowrap>
								{$form_data.create_company.label}
							</td>
							<td>
								<div class="create-company" style="width:24px; display:inline-block; float: left">
									{$form_data.create_company.html}{if $action == 'view'}&nbsp;{/if}
								</div>
								<div style="display:inline-block;width: calc(100% - 24px)" class="data">
									{if isset($form_data.create_company_name.error)}<span class="error">{$form_data.create_company_name.error}</span>{/if}{$form_data.create_company_name.html}{if $action == 'view'}&nbsp;{/if}
								</div>
							</td>
						</tr>
						{/if}
						{assign var=x value=1}
						{if $action=='view'}
							{assign var=y value=1}
						{else}
							{assign var=y value=2}
						{/if}
						{foreach key=k item=f from=$fields name=fields}
							{if $f.type!="multiselect"}
								{if !isset($focus) && $f.type=="text"}
									{assign var=focus value=$f.element}
								{/if}

								{if $y == 1 && $x >= 2}
								<td class="column" style="width: {$cols_percent}%;">
									<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
								{/if}
								{$f.full_field}
								{if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
									{if $x>$no_empty}
										<tr style="display:none;">
											<td class="label">&nbsp;</td>
											<td colspan="2" class="data">&nbsp;</td>
										</tr>
									{/if}
									{assign var=y value=1}
									{assign var=x value=$x+1}
									</table>
								</td>
								{else}
									{assign var=y value=$y+1}
								{/if}
							{/if}
						{/foreach}
		</tr>
		{if !empty($multiselects)}
			<tr>
				{assign var=x value=1}
				{assign var=y value=1}
				{foreach key=k item=f from=$multiselects name=fields}
					{if $y==1}
					<td class="column" style="width: {$cols_percent}%;">
						<table cellpadding="0" cellspacing="0" border="0" class="multiselects {if $action == 'view'}view{else}edit{/if}" style="border-top: none;">
					{/if}
					{$f.full_field}
					{if $y==$mss_rows or ($y==$mss_rows-1 and $x>$mss_no_empty)}
						{if $x>$mss_no_empty}
							<tr style="display:none;">
								<td class="label">&nbsp;</td>
								<td class="data">&nbsp;</td>
							</tr>
						{/if}
						{assign var=y value=1}
						{assign var=x value=$x+1}
						</table>
					</td>
					{else}
						{assign var=y value=$y+1}
					{/if}
				{/foreach}
			</tr>
		{/if}
		<tr>
			<td colspan="2">
			<table cellpadding="0" cellspacing="0" border="0" class="longfields {if $action == 'view'}view{else}edit{/if}" style="border-top: none;">
				{foreach key=k item=f from=$longfields name=fields}
					{$f.full_field}
				{/foreach}
			</table>
			</td>
		</tr>
	</tbody>
</table>

</div>
 		</div>
	</div>
