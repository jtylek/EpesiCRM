{if !$logged}
<center>
<table id="bar"><tr>
<td>{$status}</td>
</tr><tr>
<td class="entry">{$login}</td>
</tr></table>
</center>
{else}
<div id="menu"><table width="100%"><tr><td align="left" style="width: 20%">{$menu}</td><td style="width: 45%;">{$status}</td><td align="right" style="width: 35%">{$search}</td></tr></table></div>
<table id="bar" width="100%" cellspacing="10"><tr>
<td class="entry"><center>{$login}</center></td>
<td class="entry"><center>{$actionbar}</center></td>
<td class="entry"><center>{$homepage}</center></td>
</tr></table>
{if $main neq ""}
<div id="content">
<center style="margin: 0.5em; font-size: 0.65em;">{$main}</center>
</div>
{/if}
{/if}