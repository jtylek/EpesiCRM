{if $main_page}
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
				{if isset($new)}
					{foreach item=n from=$new}
						&nbsp;&nbsp;&nbsp;{$n}
					{/foreach}
				{/if}
			</td>
		</tr>
	</tbody>
</table>

{if isset($click2fill)}
    {$click2fill}
{/if}

{/if}

	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="css3_content_shadow">

<div class="Utils_RecordBrowser__container">

{* Outside table *}
<table class="Utils_RecordBrowser__View_entry" cellpadding="0" cellspacing="0" border="0">
	<tbody>
		<tr>
			{assign var=x value=1}
			{assign var=y value=1}
			{foreach key=k item=f from=$fields name=fields}
				{if $f.type!="multiselect"}
					{if $y==1}
					<td class="column" style="width: {$cols_percent}%;">
						<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
					{/if}
						{$f.full_field}
					{if $y==$rows or ($y==$rows-1 and $x>$no_empty)}
						{if $x>$no_empty}
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
			<td colspan="{$cols}">
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