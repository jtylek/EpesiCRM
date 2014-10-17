{if $main_page}
<table class="Utils_Tray__title" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td style="width:100px;">
				<div class="name">
					<img alt="&nbsp;" class="icon" src="{$icon}" width="32" height="32" border="0">
					<div class="label">{$caption}</div>
				</div>
			</td>
			<td class="required_fav_info">
				
			</td>
		</tr>
	</tbody>
</table>
<br>
<div class="table">
<div class="layer">
<div class="css3_content_shadow">
<div class="margin2px">
{/if}
<table class="Utils_Tray__table">
<tbody>
{foreach from=$trays item=tray}
  {if $tray.col==1}
  <tr>
  {/if}
  <td class="{$tray.class}">
      <table class="Utils_Tray__group_table">
        <thead>
          <th colspan="{$tray.slots}"><span style="margin-left:5px">{$tray.title}</span></th>
        </thead>
        <tbody>
        <tr>
          <td>
            <table id="{$tray.id}">
              <tbody>
              <tr>
              </tr>
              </tbody>
           </table>
          </td>
        </tr>
        </tbody>
      </table>
  </td>
  {if $tray.col==$tray_cols}
  </tr>
  {/if}
{/foreach}
</tbody>
</table>
{if $main_page}
</div>
</div>
</div>
</div>
{/if}